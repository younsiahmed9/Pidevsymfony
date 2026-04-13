<?php

namespace App\Controller\FrontOffice;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\CategorieRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/document', name: 'document_')]
class DocumentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository, UserRepository $userRepository): Response
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
            $qb->andWhere('d.titre LIKE :q OR d.tags LIKE :q OR d.description LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        if ($type) {
            $qb->andWhere('d.typeDocument = :type')
               ->setParameter('type', $type);
        }

        $documents = $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();

        return $this->render('frontoffice/document/index.html.twig', [
            'documents' => $documents,
            'q' => $q,
            'type' => $type,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, SluggerInterface $slugger, ValidatorInterface $validator): Response
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
            $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $slugger, false);
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

        return $this->render('frontoffice/document/new.html.twig', [
            'document' => $document,
            'categories' => $categories,
            'dossiers' => $dossiers,
            'documentFormData' => $documentFormData,
        ]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request, DocumentRepository $documentRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, SluggerInterface $slugger, ValidatorInterface $validator): JsonResponse
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

        $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $slugger, $documentId > 0);

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

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, DocumentRepository $documentRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger, ValidatorInterface $validator): Response
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
            $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $slugger, true);
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

    private function hydrateDocumentFromRequest(Document $document, Request $request, User $user, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, SluggerInterface $slugger, bool $isEdit): void
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
        $document->setTags($request->request->get('tags') !== '' ? trim((string) $request->request->get('tags')) : null);

        $dateDoc = trim((string) $request->request->get('date_document', ''));
        $document->setDateDocument($dateDoc !== '' ? new \DateTime($dateDoc) : null);

        $dateEch = trim((string) $request->request->get('date_echeance', ''));
        $document->setDateEcheance($dateEch !== '' ? new \DateTime($dateEch) : null);

        $file = $request->files->get('fichier');
        if ($file) {
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
        } elseif (!$isEdit) {
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
            'tags' => $document->getTags() ?? '',
            'description' => $document->getDescription() ?? '',
        ];
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
}