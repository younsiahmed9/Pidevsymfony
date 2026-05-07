<?php

namespace App\Controller\FrontOffice;

use App\Entity\Document;
use App\Entity\Pack;
use App\Entity\User;
use App\Repository\PackRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/pack', name: 'pack_')]
class PackController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PackRepository $packRepository
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        /** @var User $user */
        $user = $this->getUser();
        $packs = $this->packRepository->findByUser($user);

        return $this->render('frontoffice/pack/index.html.twig', [
            'packs' => $packs,
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
        
        $pack = new Pack();
        $pack->setNomPack($nom);
        $pack->setUtilisateur($user);
        $pack->setDescription($data['description'] ?? null);

        // Si des documents sont passés à la création
        if (!empty($data['documentIds'])) {
            $docRepo = $this->entityManager->getRepository(Document::class);
            foreach ($data['documentIds'] as $id) {
                $doc = $docRepo->findOneBy(['id' => $id, 'utilisateur' => $user]);
                if ($doc) {
                    $pack->addDocument($doc);
                }
            }
        }

        $this->entityManager->persist($pack);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Pack créé avec succès !',
            'packId' => $pack->getId()
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Pack $pack): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($pack->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce pack ne vous appartient pas.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $docRepo = $this->entityManager->getRepository(Document::class);
        $userDocuments = $docRepo->findBy(['utilisateur' => $user], ['createdAt' => 'DESC']);
        $availableDocuments = [];

        foreach ($userDocuments as $document) {
            if (!$pack->getDocuments()->contains($document) && !$document->isDeleted()) {
                $availableDocuments[] = $document;
            }
        }

        return $this->render('frontoffice/pack/show.html.twig', [
            'pack' => $pack,
            'availableDocuments' => $availableDocuments,
        ]);
    }

    #[Route('/{id}/add-document', name: 'add_document', methods: ['POST'])]
    public function addDocument(Pack $pack, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($pack->getUtilisateur() !== $this->getUser()) {
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

        $pack->addDocument($document);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Document ajouté au pack !']);
    }

    #[Route('/{id}/remove-document/{documentId}', name: 'remove_document', methods: ['POST'])]
    public function removeDocument(Pack $pack, int $documentId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($pack->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $docRepo = $this->entityManager->getRepository(Document::class);
        $document = $docRepo->find($documentId);

        if ($document) {
            $pack->removeDocument($document);
            $this->entityManager->flush();
        }

        return $this->json(['success' => true, 'message' => 'Document retiré du pack.']);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Pack $pack, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($pack->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$pack->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($pack);
            $this->entityManager->flush();
            $this->addFlash('success', 'Pack supprimé avec succès.');
        }

        return $this->redirectToRoute('pack_index');
    }
}
