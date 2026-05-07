<?php

namespace App\Controller\BackOffice;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Repository\CategorieRepository;
use App\Repository\DossierRepository;
use App\Service\DocumentStorageService;
use App\Service\DocumentVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

#[Route('/admin/document', name: 'admin_document_')]
class DocumentController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private DocumentStorageService $documentStorage,
        private DocumentVersionService $documentVersionService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $documents = $documentRepository->search($q);

        // Admin dashboard stats
        $stats = $documentRepository->getAdminStats();

        // Last 5 documents globally
        $recentDocs = $documentRepository->findBy(['deletedAt' => null], ['createdAt' => 'DESC'], 5);

        $html = $this->twig->render('backoffice/document/index.html.twig', [
            'documents'  => $documents,
            'q'          => $q,
            'stats'      => $stats,
            'recentDocs' => $recentDocs,
        ]);
        return new Response($html);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, \Symfony\Component\Validator\Validator\ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $document = new Document();
        $selectedUserId = null;
        $users = $userRepository->findAll();
        $categories = $categorieRepository->findAll();
        $dossiers = $dossierRepository->findAll();

        if ($request->isMethod('POST')) {
            $selectedUserId = $request->request->get('id_utilisateur');
            $user = $userRepository->find($selectedUserId);
            $categorie = $categorieRepository->find($request->request->get('id_categorie'));
            $dossier = $dossierRepository->find($request->request->get('id_dossier'));

            if ($user) $document->setUtilisateur($user);
            if ($categorie) $document->setCategorie($categorie);
            if ($dossier) $document->setDossier($dossier);

            $document->setTitre($request->request->get('titre'));
            $document->setTypeDocument($request->request->get('type_document'));
            $document->setStatut($request->request->get('statut'));
            $document->setDescription($request->request->get('description'));
            $this->syncDocumentTags($document, $request->request->get('tags'), $tagRepository);
            
            try {
                $dateDoc = $request->request->get('date_document');
                if ($dateDoc) $document->setDateDocument(new \DateTime($dateDoc));
                
                $dateEch = $request->request->get('date_echeance');
                if ($dateEch) $document->setDateEcheance(new \DateTime($dateEch));
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Format de date invalide.');
            }

            $file = $request->files->get('fichier');
            if ($file) {
                $fileInfo = $this->documentStorage->storeUploadedFile($file);
                $this->documentVersionService->registerCurrentFile($document, $this->getUser() instanceof \App\Entity\User ? $this->getUser() : null, $fileInfo, 'Import initial admin');
            }

            $document->setUpdatedAt(new \DateTime());

            $violations = $validator->validate($document);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('danger', $violation->getMessage());
                }
            } else {
                $entityManager->persist($document);
                $entityManager->flush();

                $this->addFlash('success', 'Document ajouté avec succès.');
                return $this->redirectToRoute('admin_document_index');
            }
        }

        $html = $this->twig->render('backoffice/document/new.html.twig', [
            'document' => $document,
            'users' => $users,
            'categories' => $categories,
            'dossiers' => $dossiers,
            'selectedUserId' => $selectedUserId,
        ]);
        return new Response($html);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $html = $this->twig->render('backoffice/document/show.html.twig', [
            'document' => $document,
        ]);
        return new Response($html);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $entityManager, UserRepository $userRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, TagRepository $tagRepository, \Symfony\Component\Validator\Validator\ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $selectedUserId = $document->getUtilisateur()?->getId();
        $users = $userRepository->findAll();
        $categories = $categorieRepository->findAll();
        $dossiers = $dossierRepository->findAll();

        if ($request->isMethod('POST')) {
            $selectedUserId = $request->request->get('id_utilisateur');
            $user = $userRepository->find($selectedUserId);
            $categorie = $categorieRepository->find($request->request->get('id_categorie'));
            $dossier = $dossierRepository->find($request->request->get('id_dossier'));

            if ($user) $document->setUtilisateur($user);
            if ($categorie) $document->setCategorie($categorie);
            if ($dossier) $document->setDossier($dossier);

            $document->setTitre($request->request->get('titre'));
            $document->setTypeDocument($request->request->get('type_document'));
            $document->setStatut($request->request->get('statut'));
            $document->setDescription($request->request->get('description'));
            $this->syncDocumentTags($document, $request->request->get('tags'), $tagRepository);
            
            try {
                $dateDoc = $request->request->get('date_document');
                if ($dateDoc) $document->setDateDocument(new \DateTime($dateDoc));
                
                $dateEch = $request->request->get('date_echeance');
                if ($dateEch) $document->setDateEcheance(new \DateTime($dateEch));
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Format de date invalide.');
            }

            $file = $request->files->get('fichier');
            if ($file) {
                $fileInfo = $this->documentStorage->storeUploadedFile($file);
                $this->documentVersionService->registerCurrentFile($document, $this->getUser() instanceof \App\Entity\User ? $this->getUser() : null, $fileInfo, 'Remplacement admin');
            }

            $document->setUpdatedAt(new \DateTime());

            $violations = $validator->validate($document);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('danger', $violation->getMessage());
                }
            } else {
                $entityManager->flush();

                $this->addFlash('success', 'Document mis à jour.');
                return $this->redirectToRoute('admin_document_index');
            }
        }

        $html = $this->twig->render('backoffice/document/edit.html.twig', [
            'document' => $document,
            'users' => $users,
            'categories' => $categories,
            'dossiers' => $dossiers,
            'selectedUserId' => $selectedUserId,
        ]);
        return new Response($html);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $document->setDeletedAt(new \DateTime());
            $document->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('admin_document_index');
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
}
