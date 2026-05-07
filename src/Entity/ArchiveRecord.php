<?php

namespace App\Entity;

use App\Repository\ArchiveRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArchiveRecordRepository::class)]
#[ORM\Table(name: 'archive_record')]
class ArchiveRecord
{
    public const TYPE_LEGAL = 'legal';
    public const TYPE_FUNCTIONAL = 'functional';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_archive_record', type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Document::class, inversedBy: 'archiveRecord')]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id_document', nullable: false)]
    private ?Document $document = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'archived_by_id', referencedColumnName: 'id', nullable: true)]
    private ?User $archivedBy = null;

    #[ORM\Column(name: 'archive_type', length: 50)]
    private string $archiveType = self::TYPE_FUNCTIONAL;

    #[ORM\Column(name: 'archive_reason', type: 'text', nullable: true)]
    private ?string $archiveReason = null;

    #[ORM\Column(name: 'retention_until', type: 'date', nullable: true)]
    private ?\DateTimeInterface $retentionUntil = null;

    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $archivedAt;

    #[ORM\Column(name: 'document_hash_at_archive', length: 64, nullable: true)]
    private ?string $documentHashAtArchive = null;

    #[ORM\Column(name: 'timestamp_token', length: 500, nullable: true)]
    private ?string $timestampToken = null;

    #[ORM\Column(name: 'restore_allowed', type: 'boolean')]
    private bool $restoreAllowed = true;

    #[ORM\Column(name: 'restore_count', type: 'integer')]
    private int $restoreCount = 0;

    #[ORM\Column(name: 'restored_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $restoredAt = null;

    #[ORM\Column(name: 'restored_by_id', type: 'integer', nullable: true)]
    private ?int $restoredById = null;

    public function __construct()
    {
        $this->archivedAt = new \DateTimeImmutable();
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

    public function getArchivedBy(): ?User
    {
        return $this->archivedBy;
    }

    public function setArchivedBy(?User $archivedBy): static
    {
        $this->archivedBy = $archivedBy;
        return $this;
    }

    public function getArchiveType(): string
    {
        return $this->archiveType;
    }

    public function setArchiveType(string $archiveType): static
    {
        $this->archiveType = $archiveType;
        return $this;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function setArchiveReason(?string $archiveReason): static
    {
        $this->archiveReason = $archiveReason;
        return $this;
    }

    public function getRetentionUntil(): ?\DateTimeInterface
    {
        return $this->retentionUntil;
    }

    public function setRetentionUntil(?\DateTimeInterface $retentionUntil): static
    {
        $this->retentionUntil = $retentionUntil;
        return $this;
    }

    public function getArchivedAt(): \DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(\DateTimeImmutable $archivedAt): static
    {
        $this->archivedAt = $archivedAt;
        return $this;
    }

    public function getDocumentHashAtArchive(): ?string
    {
        return $this->documentHashAtArchive;
    }

    public function setDocumentHashAtArchive(?string $documentHashAtArchive): static
    {
        $this->documentHashAtArchive = $documentHashAtArchive;
        return $this;
    }

    public function getTimestampToken(): ?string
    {
        return $this->timestampToken;
    }

    public function setTimestampToken(?string $timestampToken): static
    {
        $this->timestampToken = $timestampToken;
        return $this;
    }

    public function isRestoreAllowed(): bool
    {
        return $this->restoreAllowed;
    }

    public function setRestoreAllowed(bool $restoreAllowed): static
    {
        $this->restoreAllowed = $restoreAllowed;
        return $this;
    }

    public function getRestoreCount(): int
    {
        return $this->restoreCount;
    }

    public function setRestoreCount(int $restoreCount): static
    {
        $this->restoreCount = $restoreCount;
        return $this;
    }

    public function getRestoredAt(): ?\DateTimeImmutable
    {
        return $this->restoredAt;
    }

    public function setRestoredAt(?\DateTimeImmutable $restoredAt): static
    {
        $this->restoredAt = $restoredAt;
        return $this;
    }

    public function getRestoredById(): ?int
    {
        return $this->restoredById;
    }

    public function setRestoredById(?int $restoredById): static
    {
        $this->restoredById = $restoredById;
        return $this;
    }

    public function isLegallyArchived(): bool
    {
        return $this->archiveType === self::TYPE_LEGAL;
    }
}