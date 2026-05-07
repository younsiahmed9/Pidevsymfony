<?php

namespace App\Controller\FrontOffice;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\CategorieRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\DocumentStorageService;
use App\Service\DocumentVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Environment;

use App\Repository\PackRepository;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

#[Route('/document', name: 'document_')]
class DocumentController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private DocumentStorageService $documentStorage,
        private DocumentVersionService $documentVersionService
    ) {
    }

    #[Route('/{id}/transition/{transition}', name: 'apply_transition', methods: ['POST'])]
    public function applyTransition(
        Document $document, 
        string $transition, 
        #[Target('state_machine.fintrack_signable_document')] 
        WorkflowInterface $fintrackSignableDocumentWorkflow, 
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Ensure user can view the document first
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        // Custom permission logic for applying workflow transitions:
        // - users with DOCUMENT_EDIT (owner or admin) can perform transitions
        // - the designated signer (document.signer) may perform the 'sign' transition even if not owner
        $currentUser = $this->getUser();
        $canApply = false;

        if ($this->isGranted('DOCUMENT_EDIT', $document)) {
            $canApply = true;
        }

        // Allow designated signer to perform the 'sign' transition
        if (!$canApply && $transition === 'sign') {
            $signer = $document->getSigner();
            if ($signer !== null && $signer->getId() === $currentUser?->getId()) {
                $canApply = true;
            }
        }

        if (!$canApply) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à effectuer cette action sur ce document.');
        }

        try {
            $context = [];
            if ($transition === 'sign') {
                // For eIDAS compliance, we provide the signer info and a hash of the file
                $filePath = $this->documentStorage->resolvePath($document);
                $context = [
                    'signer_id' => $this->getUser()->getId(),
                    'document_hash' => $filePath && is_file($filePath) ? hash_file('sha256', $filePath) : null,
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

    #[Route('/{id}/sign-with-image', name: 'sign_with_image', methods: ['POST'])]
    public function signWithImage(
        Document $document,
        Request $request,
        #[Target('state_machine.fintrack_signable_document')]
        WorkflowInterface $fintrackSignableDocumentWorkflow,
        EntityManagerInterface $entityManager
    ): Response {
        // Ensure user can view the document
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Authorization: allow owners/admins or designated signer to sign
        $isAllowed = $this->isGranted('DOCUMENT_EDIT', $document) || ($document->getSigner() && $document->getSigner()->getId() === $user->getId());
        if (!$isAllowed) {
            throw $this->createAccessDeniedException('Vous n\'avez pas la permission de signer ce document.');
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $signatureData = $data['signature'] ?? null;

        if (empty($signatureData) || !is_string($signatureData)) {
            return $this->json(['success' => false, 'message' => 'Signature manquante'], 400);
        }

        // Expected data URL like "data:image/png;base64,...."
        if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $m)) {
            return $this->json(['success' => false, 'message' => 'Format de signature invalide'], 400);
        }

        $b64 = $m[2];
        $sigBin = base64_decode($b64);
        if ($sigBin === false) {
            return $this->json(['success' => false, 'message' => 'Décodage de la signature échoué'], 400);
        }

        // Save signature file
        $signDir = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'signatures';
        if (!is_dir($signDir)) {
            mkdir($signDir, 0775, true);
        }

        $filename = sprintf('sig_doc_%d_user_%d_%d.png', $document->getId(), $user->getId(), time());
        $filePath = $signDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filePath, $sigBin);

        // Compute combined hash (original file + signature)
        $originalPath = $this->documentStorage->resolvePath($document);
        $originalHash = $originalPath && is_file($originalPath) ? hash_file('sha256', $originalPath) : hash('sha256', uniqid('no_file', true));
        $sigHash = hash('sha256', $sigBin);
        $combinedHash = hash('sha256', $originalHash . $sigHash);

        // Apply workflow 'sign' if possible
        if (!$fintrackSignableDocumentWorkflow->can($document, 'sign')) {
            return $this->json(['success' => false, 'message' => 'Action non autorisée dans l\'état actuel'], 400);
        }

        try {
            $context = [
                'signer_id' => $user->getId(),
                'document_hash' => $combinedHash,
                'signed_at' => new \DateTimeImmutable(),
            ];

            $fintrackSignableDocumentWorkflow->apply($document, 'sign', $context);

            // Ensure signer and signedAt are recorded on entity (subscriber may not set object relation)
            $document->setSigner($user);
            $document->setSignedAt($context['signed_at']);
            $document->setDocumentHash($combinedHash);

            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Document signé avec succès', 'signature_file' => $filename]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/signature', name: 'signature_page', methods: ['GET'])]
    public function signaturePage(
        Document $document,
        #[Target('state_machine.fintrack_signable_document')]
        WorkflowInterface $fintrackSignableDocumentWorkflow
    ): Response {
        // Ensure user can view and sign the document
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Authorization: allow owners/admins or designated signer to sign
        $isAllowed = $this->isGranted('DOCUMENT_EDIT', $document) || ($document->getSigner() && $document->getSigner()->getId() === $user->getId());
        if (!$isAllowed) {
            throw $this->createAccessDeniedException('Vous n\'avez pas la permission de signer ce document.');
        }

        // Check if signature is possible
        $canSign = $fintrackSignableDocumentWorkflow->can($document, 'sign');
        if (!$canSign) {
            $this->addFlash('error', 'Ce document ne peut pas être signé dans son état actuel.');
            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        return $this->render('frontoffice/document/signature.html.twig', [
            'document' => $document,
            'user' => $user,
        ]);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository, UserRepository $userRepository, PackRepository $packRepository): Response
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
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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
            'packs' => $packRepository->findByUser($user),
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
            ->andWhere('d.deletedAt IS NULL')
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
    public function new(Request $request, EntityManagerInterface $entityManager, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, ValidatorInterface $validator, \App\Service\EcheanceService $echeanceService): Response
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

        // PrÃ©-remplir le dossier si passÃ© en paramÃ¨tre
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
                $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $tagRepository, false, true);
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

                    $this->addFlash('success', 'Document importÃ© avec succÃ¨s.');
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
    public function validate(Request $request, DocumentRepository $documentRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['valid' => false, 'errors' => ['general' => ['Vous devez Ãªtre connectÃ©.']]], 401);
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

        $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $tagRepository, $documentId > 0, false);

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
            'utilisateur' => $user,
            'deletedAt' => null,
        ]);

        if (!$document) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

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
            'utilisateur' => $user,
            'deletedAt' => null,
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
    public function edit(int $id, Request $request, DocumentRepository $documentRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, EntityManagerInterface $entityManager, ValidatorInterface $validator, \App\Service\EcheanceService $echeanceService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user,
            'deletedAt' => null,
        ]);

        if (!$document) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $this->denyAccessUnlessGranted('DOCUMENT_EDIT', $document);

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
                $this->hydrateDocumentFromRequest($document, $request, $user, $categorieRepository, $dossierRepository, $tagRepository, true, true);
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

                    $this->addFlash('success', 'Document mis Ã  jour.');
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
            'utilisateur' => $user,
            'deletedAt' => null,
        ]);

        if ($document && $this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $this->denyAccessUnlessGranted('DOCUMENT_DELETE', $document);
            $document->setDeletedAt(new \DateTime());
            $document->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Document supprimÃ©.');
        }

        return $this->redirectToRoute('document_index');
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(int $id, Request $request, DocumentRepository $documentRepository, EntityManagerInterface $entityManager): Response
    {
        $document = $documentRepository->find($id);
        if (!$document) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $this->denyAccessUnlessGranted('DOCUMENT_ARCHIVE', $document);

        if ($this->isCsrfTokenValid('archive'.$document->getId(), $request->request->get('_token'))) {
            $document->setStatut('archive');
            $document->setArchivedAt(new \DateTime());
            $document->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Document archivأ©.');
        }

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    public function restore(int $id, Request $request, DocumentRepository $documentRepository, EntityManagerInterface $entityManager): Response
    {
        $document = $documentRepository->find($id);
        if (!$document) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $this->denyAccessUnlessGranted('DOCUMENT_RESTORE', $document);

        if ($this->isCsrfTokenValid('restore'.$document->getId(), $request->request->get('_token'))) {
            $document->setDeletedAt(null);
            $document->setArchivedAt(null);
            $document->setStatut('valide');
            $document->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Document restaurأ©.');
        }

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(Document $document): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_DOWNLOAD', $document);

        $path = $this->documentStorage->resolvePath($document);
        if (!$path) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file($path, $document->getOriginalFilename() ?: $document->getCheminFichier());
    }

    #[Route('/{id}/preview', name: 'preview', methods: ['GET'])]
    public function preview(Document $document): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        $path = $this->documentStorage->resolvePath($document);
        if (!$path) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $document->getMimeType() ?: (mime_content_type($path) ?: 'application/octet-stream'));
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->getOriginalFilename() ?: $document->getCheminFichier()
        ));

        return $response;
    }

    #[Route('/analyze-upload', name: 'analyze_upload', methods: ['POST'])]
    public function analyzeUpload(
        Request $request,
        \App\Service\DocumentOCRService $ocrService,
        \App\Service\DocumentCategorizationService $categorizationService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $file = $request->files->get('fichier');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Aucun fichier fourni.'], 400);
        }

        try {
            // 1. Sauvegarde temporaire pour l'OCR
            $tempPath = sys_get_temp_dir() . '/' . uniqid('ocr_') . '.' . $file->guessExtension();
            $file->move(sys_get_temp_dir(), basename($tempPath));

            // 2. Extraction du texte
            $text = '';
            if ($file->getClientMimeType() === 'application/pdf') {
                $text = $ocrService->extractTextFromPDF($tempPath);
            } elseif (str_starts_with($file->getClientMimeType(), 'image/')) {
                $text = $ocrService->extractTextFromImage($tempPath);
            }

            // 3. Analyse des métadonnées
            $metadata = ['title' => '', 'description' => '', 'tags' => '', 'date_document' => '', 'date_echeance' => ''];
            $suggestedCategoryId = null;
            $suggestedFolderId = null;

            if ($text) {
                $metadata = $categorizationService->extractMetadata($text);
                
                $category = $categorizationService->suggestCategory($text, $metadata['title'] ?? '');
                if ($category) {
                    $suggestedCategoryId = $category->getId();
                }

                $folder = $categorizationService->suggestFolder($text, $metadata['title'] ?? '', $this->getUser() instanceof User ? $this->getUser() : null);
                if ($folder) {
                    $suggestedFolderId = $folder->getId();
                }
            }

            // Nettoyage
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return $this->json([
                'success' => true,
                'metadata' => $metadata,
                'suggested_category' => $suggestedCategoryId,
                'suggested_folder' => $suggestedFolderId
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function hydrateDocumentFromRequest(Document $document, Request $request, User $user, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, bool $isEdit, bool $doMove = true): void
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
            $fileInfo = $this->documentStorage->moveConvertedFile((string) $convertedFilename);
            if ($fileInfo !== null) {
                $this->documentVersionService->registerCurrentFile($document, $user, $fileInfo, $isEdit ? 'Remplacement par conversion PDF' : 'Import initial converti');
            }
        } elseif ($file && $doMove) {
            $fileInfo = $this->documentStorage->storeUploadedFile($file);
            $this->documentVersionService->registerCurrentFile($document, $user, $fileInfo, $isEdit ? 'Remplacement du fichier' : 'Import initial');
        } elseif (($file || $convertedFilename) && !$doMove) {
            // Pour la validation, on marque le fichier comme prÃ©sent sans le dÃ©placer
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


