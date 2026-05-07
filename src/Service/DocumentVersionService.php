<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DocumentVersionService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function registerCurrentFile(Document $document, ?User $actor, array $fileInfo, ?string $reason = null): DocumentVersion
    {
        $versionNumber = $document->getId() === null && $document->getVersions()->isEmpty()
            ? 1
            : $document->getCurrentVersionNumber();

        if (!$document->getVersions()->isEmpty()) {
            $versionNumber = $document->getCurrentVersionNumber() + 1;
        }

        $document
            ->setCheminFichier((string) $fileInfo['filename'])
            ->setOriginalFilename($fileInfo['original_name'] ?? null)
            ->setMimeType($fileInfo['mime_type'] ?? null)
            ->setTailleFichier(isset($fileInfo['size']) ? (int) $fileInfo['size'] : null)
            ->setChecksum($fileInfo['checksum'] ?? null)
            ->setCurrentVersionNumber($versionNumber)
            ->setUpdatedAt(new \DateTime());

        $version = (new DocumentVersion())
            ->setDocument($document)
            ->setVersionNumber($versionNumber)
            ->setFilename((string) $fileInfo['filename'])
            ->setOriginalFilename($fileInfo['original_name'] ?? null)
            ->setMimeType($fileInfo['mime_type'] ?? null)
            ->setFileSize(isset($fileInfo['size']) ? (int) $fileInfo['size'] : null)
            ->setChecksum($fileInfo['checksum'] ?? null)
            ->setCreatedBy($actor)
            ->setChangeReason($reason);

        $document->addVersion($version);
        $this->entityManager->persist($version);

        return $version;
    }
}
