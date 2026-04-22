<?php

namespace App\Controller\FrontOffice;

use App\Entity\Document;
use App\Entity\DocumentBundle;
use App\Entity\User;
use App\Repository\DocumentBundleRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bundle', name: 'bundle_')]
class BundleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentBundleRepository $bundleRepository
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        /** @var User $user */
        $user = $this->getUser();
        $bundles = $this->bundleRepository->findByUser($user);

        return $this->render('frontoffice/bundle/index.html.twig', [
            'bundles' => $bundles,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $nom = $data['nom'] ?? '';

        if (empty($nom)) {
            return $this->json(['success' => false, 'message' => 'Le nom du pack est obligatoire.'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        $bundle = new DocumentBundle();
        $bundle->setNomBundle($nom);
        $bundle->setUtilisateur($user);
        $bundle->setDescription($data['description'] ?? null);

        // Si des documents sont passés à la création
        if (!empty($data['documentIds'])) {
            $docRepo = $this->entityManager->getRepository(Document::class);
            foreach ($data['documentIds'] as $id) {
                $doc = $docRepo->findOneBy(['id' => $id, 'utilisateur' => $user]);
                if ($doc) {
                    $bundle->addDocument($doc);
                }
            }
        }

        $this->entityManager->persist($bundle);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Pack créé avec succès !',
            'bundleId' => $bundle->getId()
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(DocumentBundle $bundle): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($bundle->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce pack ne vous appartient pas.');
        }

        return $this->render('frontoffice/bundle/show.html.twig', [
            'bundle' => $bundle,
        ]);
    }

    #[Route('/{id}/add-document', name: 'add_document', methods: ['POST'])]
    public function addDocument(DocumentBundle $bundle, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($bundle->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $documentId = $data['documentId'] ?? null;

        if (!$documentId) {
            return $this->json(['success' => false, 'message' => 'Document manquant'], 400);
        }

        $docRepo = $this->entityManager->getRepository(Document::class);
        $document = $docRepo->findOneBy(['id' => $documentId, 'utilisateur' => $this->getUser()]);

        if (!$document) {
            return $this->json(['success' => false, 'message' => 'Document introuvable'], 404);
        }

        $bundle->addDocument($document);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Document ajouté au pack !']);
    }

    #[Route('/{id}/remove-document/{documentId}', name: 'remove_document', methods: ['POST'])]
    public function removeDocument(DocumentBundle $bundle, int $documentId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($bundle->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $docRepo = $this->entityManager->getRepository(Document::class);
        $document = $docRepo->find($documentId);

        if ($document) {
            $bundle->removeDocument($document);
            $this->entityManager->flush();
        }

        return $this->json(['success' => true, 'message' => 'Document retiré du pack.']);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(DocumentBundle $bundle, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($bundle->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$bundle->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($bundle);
            $this->entityManager->flush();
            $this->addFlash('success', 'Pack supprimé avec succès.');
        }

        return $this->redirectToRoute('bundle_index');
    }
}
