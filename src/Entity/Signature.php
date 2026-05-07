<?php

namespace App\Entity;

use App\Repository\SignatureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignatureRepository::class)]
#[ORM\Table(name: 'signature')]
class Signature
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_ADVANCED = 'advanced';
    public const TYPE_QUALIFIED = 'qualified';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_signature', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'signatures')]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id_document', nullable: false)]
    private ?Document $document = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'signer_id', referencedColumnName: 'id', nullable: false)]
    private ?User $signer = null;

    #[ORM\Column(name: 'signature_type', length: 50)]
    private string $signatureType = self::TYPE_SIMPLE;

    #[ORM\Column(name: 'status', length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'signature_value', length: 500, nullable: true)]
    private ?string $signatureValue = null;

    #[ORM\Column(name: 'certificate_data', type: 'text', nullable: true)]
    private ?string $certificateData = null;

    #[ORM\Column(name: 'signature_proof_url', length: 500, nullable: true)]
    private ?string $signatureProofUrl = null;

    #[ORM\Column(name: 'signed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $signedAt = null;

    #[ORM\Column(name: 'document_hash_before', length: 64, nullable: true)]
    private ?string $documentHashBefore = null;

    #[ORM\Column(name: 'document_hash_after', length: 64, nullable: true)]
    private ?string $documentHashAfter = null;

    #[ORM\Column(name: 'signing_order', type: 'integer')]
    private int $signingOrder = 1;

    #[ORM\Column(name: 'signature_policy', length: 100, nullable: true)]
    private ?string $signaturePolicy = null;

    #[ORM\Column(name: 'callback_url', length: 500, nullable: true)]
    private ?string $callbackUrl = null;

    #[ORM\Column(name: 'provider_reference', length: 255, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'rejection_reason', type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;
        return $this;
    }

    public function getSigner(): ?User
    {
        return $this->signer;
    }

    public function setSigner(?User $signer): static
    {
        $this->signer = $signer;
        return $this;
    }

    public function getSignatureType(): string
    {
        return $this->signatureType;
    }

    public function setSignatureType(string $signatureType): static
    {
        $this->signatureType = $signatureType;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSignatureValue(): ?string
    {
        return $this->signatureValue;
    }

    public function setSignatureValue(?string $signatureValue): static
    {
        $this->signatureValue = $signatureValue;
        return $this;
    }

    public function getCertificateData(): ?string
    {
        return $this->certificateData;
    }

    public function setCertificateData(?string $certificateData): static
    {
        $this->certificateData = $certificateData;
        return $this;
    }

    public function getSignatureProofUrl(): ?string
    {
        return $this->signatureProofUrl;
    }

    public function setSignatureProofUrl(?string $signatureProofUrl): static
    {
        $this->signatureProofUrl = $signatureProofUrl;
        return $this;
    }

    public function getSignedAt(): ?\DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(?\DateTimeImmutable $signedAt): static
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getDocumentHashBefore(): ?string
    {
        return $this->documentHashBefore;
    }

    public function setDocumentHashBefore(?string $documentHashBefore): static
    {
        $this->documentHashBefore = $documentHashBefore;
        return $this;
    }

    public function getDocumentHashAfter(): ?string
    {
        return $this->documentHashAfter;
    }

    public function setDocumentHashAfter(?string $documentHashAfter): static
    {
        $this->documentHashAfter = $documentHashAfter;
        return $this;
    }

    public function getSigningOrder(): int
    {
        return $this->signingOrder;
    }

    public function setSigningOrder(int $signingOrder): static
    {
        $this->signingOrder = $signingOrder;
        return $this;
    }

    public function getSignaturePolicy(): ?string
    {
        return $this->signaturePolicy;
    }

    public function setSignaturePolicy(?string $signaturePolicy): static
    {
        $this->signaturePolicy = $signaturePolicy;
        return $this;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    public function setCallbackUrl(?string $callbackUrl): static
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function setProviderReference(?string $providerReference): static
    {
        $this->providerReference = $providerReference;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function markUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }
}