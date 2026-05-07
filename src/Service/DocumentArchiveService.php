<?php

namespace App\Service;

use App\Entity\ArchiveRecord;
use App\Entity\Document;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DocumentArchiveService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function archiveDocument(Document $document, User $archivedBy, string $reason = null, string $policy = ArchiveRecord::TYPE_FUNCTIONAL, ?\DateTimeInterface $retentionUntil = null): ArchiveRecord
    {
        $record = new ArchiveRecord();
        $record->setDocument($document)
            ->setArchivedBy($archivedBy)
            ->setArchiveType($policy)
            ->setArchiveReason($reason)
            ->setRetentionUntil($retentionUntil)
            ->setDocumentHashAtArchive($document->getChecksum());

        if ($policy === ArchiveRecord::TYPE_LEGAL && $retentionUntil === null) {
            $retentionUntil = new \DateTime('+10 years');
            $record->setRetentionUntil($retentionUntil);
        }

        $document->setStatut('archive');
        $document->setArchivedAt(new \DateTimeImmutable());
        $document->setArchiveRecord($record);

        $this->entityManager->persist($record);
        $this->entityManager->flush();

        return $record;
    }

    public function restoreDocument(Document $document, User $restoredBy): void
    {
        $archiveRecord = $document->getArchiveRecord();
        if ($archiveRecord && $archiveRecord->isRestoreAllowed()) {
            $archiveRecord->setRestoredAt(new \DateTimeImmutable())
                ->setRestoredById($restoredBy->getId())
                ->setRestoreCount($archiveRecord->getRestoreCount() + 1);

            $document->setStatut('signed');
            $document->setArchivedAt(null);
            $document->setSignatureState('signed');

            $this->entityManager->flush();
        }
    }

    public function canRestore(Document $document): bool
    {
        $archiveRecord = $document->getArchiveRecord();
        return $archiveRecord && $archiveRecord->isRestoreAllowed();
    }

    public function purgeExpiredLegalArchives(\DateTimeInterface $before): int
    {
        $records = $this->entityManager->createQueryBuilder()
            ->select('ar')
            ->from(ArchiveRecord::class, 'ar')
            ->where('ar.archiveType = :type')
            ->andWhere('ar.retentionUntil IS NOT NULL')
            ->andWhere('ar.retentionUntil <= :before')
            ->setParameter('type', ArchiveRecord::TYPE_LEGAL)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($records as $record) {
            $document = $record->getDocument();
            if ($document) {
                $this->entityManager->remove($document);
                $count++;
            }
        }

        $this->entityManager->flush();
        return $count;
    }
}