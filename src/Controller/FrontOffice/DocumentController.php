<?php

namespace App\Controller\FrontOffice;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\CategorieRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/document', name: 'document_')]
class DocumentController extends AbstractController
{
    #[Route('/{id}/transition/{transition}', name: 'apply_transition', methods: ['POST'])]
    public function applyTransition(
        Document $document, 
        string $transition, 
        #[Target('state_machine.fintrack_signable_document')] 
        WorkflowInterface $fintrackSignableDocumentWorkflow, 
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Security: Ensure the document belongs to the user OR user is allowed to sign
        if ($document->getUtilisateur() !== $this->getUser()) {
             throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce document.');
        }

        try {
            $context = [];
            if ($transition === 'sign') {
                // For eIDAS compliance, we provide the signer info and a hash of the file
                $context = [
                    'signer_id' => $this->getUser()->getId(),
                    'document_hash' => hash_file('sha256', $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getCheminFichier()),
                ];
            }

            if ($fintrackSignableDocumentWorkflow->can($document, $transition)) {
                $fintrackSignableDocumentWorkflow->apply($document, $transition, $context);
                $entityManager->flush();
                
                $this->addFlash('success', sprintf('Le document est désormais en état : %s', $document->getSignatureState()));
            } else {
                $this->addFlash('error', 'Cette action n\'est pas possible dans l\'état actuel.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du changement d\'état : ' . $e->getMessage());
        }

        return $this->redirectToRoute('document_index');
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository, UserRepository $userRepository, \App\Repository\DocumentBundleRepository $bundleRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $q = $request->query->get('q', '');
        $type = $request->query->get('type', '');

        $qb = $documentRepository->createQueryBuilder('d')
            ->where('d.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $user);

        if ($q) {
            $normalizedQuery = mb_strtolower($q);

            $qb->leftJoin('d.tags', 't')
               ->addSelect('t')
               ->distinct()
               ->andWhere('LOWER(d.titre) LIKE :q OR LOWER(t.nomTag) LIKE :q OR LOWER(d.description) LIKE :q')
               ->setParameter('q', '%'.$normalizedQuery.'%');
        }

        if ($type) {
            $qb->andWhere('d.typeDocument = :type')
               ->setParameter('type', $type);
        }

        $documents = $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();

        // Dashboard stats
        $stats = $documentRepository->getStats($user);

        // Recent 5 documents (regardless of filter)
        $recentDocs = $documentRepository->findBy(['utilisateur' => $user], ['createdAt' => 'DESC'], 5);

        // Expiring within 30 days (user-scoped)
        $expiring = $documentRepository->createQueryBuilder('d')
            ->where('d.utilisateur = :user')
            ->andWhere('d.dateEcheance IS NOT NULL')
            ->andWhere('d.dateEcheance <= :limit')
            ->andWhere('d.dateEcheance >= :today')
            ->andWhere('d.statut != :archive')
            ->setParameter('user', $user)
            ->setParameter('limit', new \DateTime('+30 days'))
            ->setParameter('today', new \DateTime())
            ->setParameter('archive', 'archive')
            ->orderBy('d.dateEcheance', 'ASC')
            ->setMaxResults(5)
            ->getQuery()->getResult();

        return $this->render('frontoffice/document/index.html.twig', [
            'documents' => $documents,
            'bundles' => $bundleRepository->findByUser($user),
            'q' => $q,
            'type' => $type,
            'stats' => $stats,
            'recentDocs' => $recentDocs,
            'expiringDocs' => $expiring,
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $q = $request->query->get('q', '');
        $type = $request->query->get('type', '');

        $qb = $documentRepository->createQueryBuilder('d')
            ->where('d.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $user);

        if ($q) {
            $qb->leftJoin('d.tags', 't')
               ->andWhere('LOWER(d.titre) LIKE :q OR LOWER(t.nomTag) LIKE :q OR LOWER(d.description) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        if ($type) {
            $qb->andWhere('d.typeDocument = :type')
               ->setParameter('type', $type);
        }

        $documents = $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
        $stats = $documentRepository->getStats($user);

        $html = $this->renderView('frontoffice/document/export_pdf.html.twig', [
            'documents' => $documents,
            'stats' => $stats,
            'user' => $user,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => (new ResponseHeaderBag())->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'fintrack_documents_' . date('Y-m-d') . '.pdf'
                ),
            ]
        );
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, SluggerInterface $slugger, ValidatorInterface $validator, \App\Service\EcheanceService $echeanceService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $document = new Document();
        $categories = $categorieRepository->findAll();
        $dossiers = $dossierRepository->findBy(['utilisateur' => $user]);
        $documentFormData = $this->createDocumentFormData($document);

        // Pré-remplir le dossier si passé en paramètre
        $dossierId = $request->query->get('dossier');
        if ($dossierId) {
            $preselectedDossier = $dossierRepository->find($dossierId);
            if ($preselectedDossier && $preselectedDossier->getUtilisateur() === $user) {
                $document->setDossier($preselectedDossier);
                $documentFormData['id_dossier'] = (string) $preselectedDossier->getId();
            }
        }

        if ($request->isMethod('POST')) {
            $fileError = $this->validateUploadedDocumentFile($request->files->get('fichier'));
            if ($fileError !== null) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => false,
                        'errors' => ['fichier' => [$fileError]],
                    ], 422);
                }

                $this->addFlash('form_error', $fileError);
            } else {
                $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $tagRepository, $slugger, false, true);
                $documentFormData = $this->createDocumentFormData($document);

                $violations = $validator->validate($document);
                if (count($violations) > 0) {
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'valid' => false,
                            'errors' => $this->normalizeViolations($violations),
                        ], 422);
                    }

                    foreach ($violations as $violation) {
                        $this->addFlash('form_error', (string) $violation->getMessage());
                    }
                } else {
                    $document->setUpdatedAt(new \DateTime());

                    $entityManager->persist($document);
                    $entityManager->flush();

                    // Auto-sync echeance if date is set
                    $echeanceService->syncFromDocument($document);
                    $entityManager->flush();

                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'valid' => true,
                            'redirect' => $this->generateUrl('document_index'),
                        ]);
                    }

                    $this->addFlash('success', 'Document importé avec succès.');
                    return $this->redirectToRoute('document_index');
                }
            }
        }

        return $this->render('frontoffice/document/new.html.twig', [
            'document' => $document,
            'categories' => $categories,
            'dossiers' => $dossiers,
            'documentFormData' => $documentFormData,
        ]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request, DocumentRepository $documentRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, SluggerInterface $slugger, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['valid' => false, 'errors' => ['general' => ['Vous devez être connecté.']]], 401);
        }

        $documentId = (int) $request->request->get('document_id', 0);
        $document = $documentId > 0
            ? $documentRepository->findOneBy(['id' => $documentId, 'utilisateur' => $user])
            : new Document();

        if (!$document instanceof Document) {
            return $this->json(['valid' => false, 'errors' => ['general' => ['Document introuvable.']]], 404);
        }

        $fileError = $this->validateUploadedDocumentFile($request->files->get('fichier'));
        if ($fileError !== null) {
            return $this->json(['valid' => false, 'errors' => ['fichier' => [$fileError]]], 422);
        }

        $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $tagRepository, $slugger, $documentId > 0, false);

        $violations = $validator->validate($document);

        return $this->json([
            'valid' => count($violations) === 0,
            'errors' => $this->normalizeViolations($violations),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, DocumentRepository $documentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if (!$document) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        return $this->render('frontoffice/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/data', name: 'data', methods: ['GET'])]
    public function data(int $id, DocumentRepository $documentRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $document = $documentRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if (!$document) {
            return $this->json(['error' => 'Document not found'], 404);
        }

        return $this->json([
            'id' => $document->getId(),
            'titre' => $document->getTitre(),
            'date_echeance' => $document->getDateEcheance() ? $document->getDateEcheance()->format('Y-m-d') : null,
            'description' => $document->getDescription(),
            'type_document' => $document->getTypeDocument(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, DocumentRepository $documentRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger, ValidatorInterface $validator, \App\Service\EcheanceService $echeanceService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if (!$document) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $categories = $categorieRepository->findAll();
        $dossiers = $dossierRepository->findBy(['utilisateur' => $user]);
        $documentFormData = $this->createDocumentFormData($document);

        if ($request->isMethod('POST')) {
            $fileError = $this->validateUploadedDocumentFile($request->files->get('fichier'));
            if ($fileError !== null) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => false,
                        'errors' => ['fichier' => [$fileError]],
                    ], 422);
                }

                $this->addFlash('form_error', $fileError);
            } else {
                $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $tagRepository, $slugger, true, true);
                $documentFormData = $this->createDocumentFormData($document);

                $violations = $validator->validate($document);
                if (count($violations) > 0) {
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'valid' => false,
                            'errors' => $this->normalizeViolations($violations),
                        ], 422);
                    }

                    foreach ($violations as $violation) {
                        $this->addFlash('form_error', (string) $violation->getMessage());
                    }
                } else {
                    $document->setUpdatedAt(new \DateTime());

                    $entityManager->flush();

                    // Auto-sync echeance if date is set
                    $echeanceService->syncFromDocument($document);
                    $entityManager->flush();

                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'valid' => true,
                            'redirect' => $this->generateUrl('document_index'),
                        ]);
                    }

                    $this->addFlash('success', 'Document mis à jour.');
                    return $this->redirectToRoute('document_index');
                }
            }
        }

        return $this->render('frontoffice/document/edit.html.twig', [
            'document' => $document,
            'categories' => $categories,
            'dossiers' => $dossiers,
            'documentFormData' => $documentFormData,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, DocumentRepository $documentRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if ($document && $this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $entityManager->remove($document);
            $entityManager->flush();
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('document_index');
    }

    private function hydrateDocumentFromRequest(Document $document, Request $request, User $user, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, SluggerInterface $slugger, bool $isEdit, bool $doMove = true): void
    {
        $document->setUtilisateur($user);

        $categorieId = $request->request->get('id_categorie');
        if ($categorieId !== null && $categorieId !== '') {
            $categorie = $categorieRepository->find($categorieId);
            if ($categorie) {
                $document->setCategorie($categorie);
            }
        } else {
            $document->setCategorie(null);
        }

        $dossierId = $request->request->get('id_dossier');
        if ($dossierId !== null && $dossierId !== '') {
            $dossier = $dossierRepository->find($dossierId);
            if ($dossier && $dossier->getUtilisateur() === $user) {
                $document->setDossier($dossier);
            }
        } else {
            $document->setDossier(null);
        }

        $document->setTitre(trim((string) $request->request->get('titre', '')));
        $document->setTypeDocument(trim((string) $request->request->get('type_document', '')));
        $document->setStatut(trim((string) $request->request->get('statut', 'valide')));
        $document->setDescription($request->request->get('description') !== '' ? trim((string) $request->request->get('description')) : null);
        $this->syncDocumentTags($document, $request->request->get('tags'), $tagRepository);

        $dateDoc = trim((string) $request->request->get('date_document', ''));
        $document->setDateDocument($dateDoc !== '' ? new \DateTime($dateDoc) : null);

        $dateEch = trim((string) $request->request->get('date_echeance', ''));
        $document->setDateEcheance($dateEch !== '' ? new \DateTime($dateEch) : null);

        $file = $request->files->get('fichier');
        $convertedFilename = $request->request->get('converted_filename');

        if ($convertedFilename && $doMove) {
            $document->setCheminFichier($convertedFilename);
            $filePath = $this->getParameter('documents_directory') . '/' . $convertedFilename;
            if (file_exists($filePath)) {
                $document->setTailleFichier(filesize($filePath));
            }
        } elseif ($file && $doMove) {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $extension = $file->guessExtension() ?: 'bin';
            $fileSize = $file->getSize();
            $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;

            $file->move(
                $this->getParameter('documents_directory'),
                $newFilename
            );
            $document->setCheminFichier($newFilename);
            $document->setTailleFichier($fileSize);
        } elseif (($file || $convertedFilename) && !$doMove) {
            // Pour la validation, on marque le fichier comme présent sans le déplacer
            if (!$document->getCheminFichier()) {
                $document->setCheminFichier('pending_validation');
            }
        } elseif (!$isEdit && !$document->getCheminFichier()) {
            $document->setCheminFichier('');
        }
    }

    private function createDocumentFormData(Document $document): array
    {
        return [
            'id_document' => $document->getId() ? (string) $document->getId() : '',
            'id_categorie' => $document->getCategorie() ? (string) $document->getCategorie()->getId() : '',
            'id_dossier' => $document->getDossier() ? (string) $document->getDossier()->getId() : '',
            'titre' => $document->getId() ? $document->getTitre() : '',
            'type_document' => $document->getId() ? $document->getTypeDocument() : 'contrat',
            'statut' => $document->getId() ? $document->getStatut() : 'valide',
            'date_document' => $document->getDateDocument() ? $document->getDateDocument()->format('Y-m-d') : '',
            'date_echeance' => $document->getDateEcheance() ? $document->getDateEcheance()->format('Y-m-d') : '',
            'tags' => $document->getTagsAsString(),
            'description' => $document->getDescription() ?? '',
        ];
    }

    private function syncDocumentTags(Document $document, mixed $tagsInput, TagRepository $tagRepository): void
    {
        $document->clearTags();

        foreach ($this->normalizeTagValues($tagsInput) as $rawTag) {
            $tag = ctype_digit($rawTag)
                ? $tagRepository->find((int) $rawTag)
                : null;

            if (!$tag) {
                $tag = $tagRepository->findOrCreateByName($rawTag);
            }

            if ($tag) {
                $document->addTag($tag);
            }
        }
    }

    private function normalizeTagValues(mixed $tagsInput): array
    {
        if (is_array($tagsInput)) {
            $values = $tagsInput;
        } else {
            $values = preg_split('/[;,\n]+/', (string) $tagsInput) ?: [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $tag = trim((string) $value);

            if ($tag === '') {
                continue;
            }

            $key = mb_strtolower($tag);
            $normalized[$key] = $tag;
        }

        return array_values($normalized);
    }

    private function normalizeViolations(iterable $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = (string) $violation->getPropertyPath();
            $field = match ($propertyPath) {
                'titre' => 'titre',
                'typeDocument' => 'type_document',
                'cheminFichier' => 'fichier',
                'statut' => 'statut',
                'dateDocument' => 'date_document',
                'dateEcheance' => 'date_echeance',
                'description' => 'description',
                'tags' => 'tags',
                'categorie' => 'id_categorie',
                'dossier' => 'id_dossier',
                default => 'general',
            };

            $errors[$field][] = (string) $violation->getMessage();
        }

        return $errors;
    }

    private function validateUploadedDocumentFile(mixed $file): ?string
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        ];

        $mimeType = (string) $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            return 'Veuillez importer un fichier PDF, JPG, PNG, DOCX ou XLSX valide.';
        }

        $extension = strtolower((string) $file->guessExtension());
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'], true)) {
            return 'Veuillez importer un fichier PDF, JPG, PNG, DOCX ou XLSX valide.';
        }

        return null;
    }
}