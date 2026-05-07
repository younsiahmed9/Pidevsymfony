<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Signature;
use App\Entity\Signatory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DocumentSignService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function initiateSignature(
        Document $document,
        User $initiator,
        array $signers,
        string $signatureType = Signature::TYPE_SIMPLE,
        string $policy = null,
        string $callbackUrl = null
    ): Signature {
        $signature = new Signature();
        $signature->setDocument($document)
            ->setSigner($initiator)
            ->setSignatureType($signatureType)
            ->setStatus(Signature::STATUS_PENDING)
            ->setSignaturePolicy($policy)
            ->setCallbackUrl($callbackUrl);

        $document->setSignatureState('signing_in_progress');
        $document->addSignature($signature);

        foreach ($signers as $index => $signerData) {
            $signatory = new Signatory();
            $signatory->setDocument($document)
                ->setSigningOrder($signerData['order'] ?? $index + 1)
                ->setRole($signerData['role'] ?? Signatory::ROLE_APPROVER);

            if (isset($signerData['user_id'])) {
                $signatory->setUser($this->entityManager->getReference(User::class, $signerData['user_id']));
            }

            if (isset($signerData['email'])) {
                $signatory->setEmail($signerData['email']);
            }

            $document->addSignatory($signatory);
        }

        $this->entityManager->persist($signature);
        $this->entityManager->flush();

        return $signature;
    }

    public function handleSignatureCallback(array $payload): ?Signature
    {
        $documentId = $payload['document_id'] ?? null;
        $signatureId = $payload['signature_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$documentId && !$signatureId) {
            return null;
        }

        $signature = $signatureId
            ? $this->entityManager->getRepository(Signature::class)->findOneBy(['providerReference' => $signatureId])
            : $this->entityManager->getRepository(Signature::class)->findOneBy(['document' => $documentId, 'status' => Signature::STATUS_PENDING]);

        if (!$signature) {
            return null;
        }

        if ($status === 'signed') {
            $signature->setStatus(Signature::STATUS_SIGNED);
            $signature->setSignedAt(new \DateTimeImmutable($payload['signed_at'] ?? 'now'));
            $signature->setSignatureProofUrl($payload['signature_proof_url'] ?? null);
            $signature->setSignatureValue($payload['signature_value'] ?? null);
            $signature->setCertificateData($payload['certificate_data'] ?? null);
            $signature->setDocumentHashAfter($payload['document_hash'] ?? null);

            $document = $signature->getDocument();
            $document->setSignatureState('signed');
            $document->setSigner($signature->getSigner());
            $document->setSignedAt(new \DateTimeImmutable());
            $document->setDocumentHash($signature->getDocumentHashAfter());
        } elseif ($status === 'rejected') {
            $signature->setStatus(Signature::STATUS_REJECTED);
            $signature->setRejectionReason($payload['rejection_reason'] ?? 'Rejected by provider');
        }

        $this->entityManager->flush();

        return $signature;
    }

    public function markSignatureAsSigned(
        Document $document,
        User $signer,
        string $signatureValue,
        string $certificateData = null,
        string $proofUrl = null
    ): Signature {
        $signature = new Signature();
        $signature->setDocument($document)
            ->setSigner($signer)
            ->setSignatureType(Signature::TYPE_SIMPLE)
            ->setStatus(Signature::STATUS_SIGNED)
            ->setSignatureValue($signatureValue)
            ->setCertificateData($certificateData)
            ->setSignatureProofUrl($proofUrl)
            ->setSignedAt(new \DateTimeImmutable())
            ->setDocumentHashBefore($document->getChecksum())
            ->setDocumentHashAfter(hash('sha256', $signatureValue));

        $document->setSignatureState('signed');
        $document->setSigner($signer);
        $document->setSignedAt(new \DateTimeImmutable());
        $document->addSignature($signature);

        $this->entityManager->persist($signature);
        $this->entityManager->flush();

        return $signature;
    }

    public function getSignaturesByDocument(Document $document): array
    {
        return $this->entityManager->getRepository(Signature::class)->findByDocument($document->getId());
    }

    public function getPendingSignaturesBySigner(User $user): array
    {
        return $this->entityManager->getRepository(Signature::class)->findPendingBySigner($user->getId());
    }

    public function validateSignatureRequest(Document $document): array
    {
        $errors = [];

        if ($document->isDeleted()) {
            $errors[] = 'Document is deleted and cannot be signed.';
        }

        if ($document->getSignatureState() === 'signed') {
            $errors[] = 'Document is already signed.';
        }

        if (empty($document->getCheminFichier())) {
            $errors[] = 'Document has no file attached.';
        }

        return $errors;
    }
}