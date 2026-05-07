<?php

namespace App\Entity;

use App\Repository\DocumentVersionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentVersionRepository::class)]
#[ORM\Table(name: 'document_version')]
#[ORM\UniqueConstraint(name: 'uniq_document_version_number', columns: ['document_id', 'version_number'])]
class DocumentVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_document_version', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id_document', nullable: false, onDelete: 'CASCADE')]
    private ?Document $document = null;

    #[ORM\Column(name: 'version_number', type: 'integer')]
    private int $versionNumber = 1;

    #[ORM\Column(name: 'filename', length: 500)]
    private string $filename = '';

    #[ORM\Column(name: 'original_filename', length: 255, nullable: true)]
    private ?string $originalFilename = null;

    #[ORM\Column(name: 'mime_type', length: 150, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(name: 'file_size', type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(name: 'checksum', length: 64, nullable: true)]
    private ?string $checksum = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'change_reason', length: 255, nullable: true)]
    private ?string $changeReason = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getDocument(): ?Document { return $this->document; }
    public function setDocument(?Document $document): static { $this->document = $document; return $this; }
    public function getVersionNumber(): int { return $this->versionNumber; }
    public function setVersionNumber(int $versionNumber): static { $this->versionNumber = $versionNumber; return $this; }
    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }
    public function getOriginalFilename(): ?string { return $this->originalFilename; }
    public function setOriginalFilename(?string $originalFilename): static { $this->originalFilename = $originalFilename; return $this; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static { $this->mimeType = $mimeType; return $this; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $fileSize): static { $this->fileSize = $fileSize; return $this; }
    public function getChecksum(): ?string { return $this->checksum; }
    public function setChecksum(?string $checksum): static { $this->checksum = $checksum; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }
    public function getChangeReason(): ?string { return $this->changeReason; }
    public function setChangeReason(?string $changeReason): static { $this->changeReason = $changeReason; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
