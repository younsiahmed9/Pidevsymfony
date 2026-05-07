<?php

namespace App\Controller\Api;

use App\Entity\ArchiveRecord;
use App\Entity\Document;
use App\Entity\Signature;
use App\Entity\Signatory;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\SignaturePolicyRepository;
use App\Service\DocumentArchiveService;
use App\Service\DocumentSignService;
use App\Service\DocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/documents', name: 'api_documents_')]
class DocumentApiController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(DocumentRepository $documentRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        $documents = $documentRepository->findBy(['utilisateur' => $user], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn(Document $d) => [
            'id' => $d->getId(),
            'title' => $d->getTitre(),
            'type' => $d->getTypeDocument(),
            'status' => $d->getStatut(),
            'signature_state' => $d->getSignatureState(),
            'created_at' => $d->getCreatedAt()->format('c'),
            'date_echeance' => $d->getDateEcheance()?->format('Y-m-d'),
        ], $documents));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $document = new Document();
        $document->setUtilisateur($user)
            ->setTitre($data['title'] ?? '')
            ->setTypeDocument($data['type'] ?? 'document')
            ->setDescription($data['description'] ?? null)
            ->setStatut('draft');

        $errors = $validator->validate($document);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'errors' => array_map(fn($e) => $e->getMessage(), iterator_to_array($errors)),
            ], 422);
        }

        $entityManager->persist($document);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $document->getId(),
            'title' => $document->getTitre(),
        ], 201);
    }

    #[Route('/{id}/sign', name: 'sign_initiate', methods: ['POST'])]
    public function initiateSignature(
        Document $document,
        Request $request,
        DocumentSignService $signService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('DOCUMENT_SIGN', $document);

        $data = json_decode($request->getContent(), true);
        $signers = $data['signers'] ?? [];

        $errors = $signService->validateSignatureRequest($document);
        if (!empty($errors)) {
            return $this->json(['success' => false, 'errors' => $errors], 422);
        }

        $signature = $signService->initiateSignature(
            $document,
            $this->getUser(),
            $signers,
            $data['signature_type'] ?? Signature::TYPE_SIMPLE,
            $data['signing_policy'] ?? null,
            $data['callback_url'] ?? null
        );

        return $this->json([
            'success' => true,
            'signature_id' => $signature->getId(),
            'document_id' => $document->getId(),
            'status' => $signature->getStatus(),
        ], 201);
    }

    #[Route('/signatures/callback', name: 'signature_callback', methods: ['POST'])]
    public function signatureCallback(
        Request $request,
        DocumentSignService $signService
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        $signature = $signService->handleSignatureCallback($payload);

        if (!$signature) {
            return $this->json(['success' => false, 'message' => 'Signature not found'], 404);
        }

        return $this->json([
            'success' => true,
            'signature_id' => $signature->getId(),
            'document_id' => $signature->getDocument()?->getId(),
            'status' => $signature->getStatus(),
        ]);
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(
        Document $document,
        Request $request,
        DocumentArchiveService $archiveService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('DOCUMENT_ARCHIVE', $document);

        $data = json_decode($request->getContent(), true);

        $archiveRecord = $archiveService->archiveDocument(
            $document,
            $this->getUser(),
            $data['reason'] ?? null,
            $data['policy'] ?? ArchiveRecord::TYPE_FUNCTIONAL,
            isset($data['retain_until']) ? new \DateTime($data['retain_until']) : null
        );

        return $this->json([
            'success' => true,
            'archive_id' => $archiveRecord->getId(),
            'policy' => $archiveRecord->getArchiveType(),
            'retention_until' => $archiveRecord->getRetentionUntil()?->format('Y-m-d'),
        ]);
    }

    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    public function restore(
        Document $document,
        DocumentArchiveService $archiveService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('DOCUMENT_RESTORE', $document);

        if (!$archiveService->canRestore($document)) {
            return $this->json(['success' => false, 'message' => 'Document cannot be restored'], 400);
        }

        $archiveService->restoreDocument($document, $this->getUser());

        return $this->json(['success' => true]);
    }

    #[Route('/policies', name: 'signature_policies', methods: ['GET'])]
    public function getSignaturePolicies(SignaturePolicyRepository $policyRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json($policyRepository->findAll());
    }
}