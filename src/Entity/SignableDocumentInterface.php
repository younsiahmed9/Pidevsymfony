<?php

namespace App\Entity;

interface SignableDocumentInterface
{
    public const STATE_DRAFT = 'draft';
    public const STATE_PENDING_APPROVAL = 'pending_approval';
    public const STATE_SIGNING_IN_PROGRESS = 'signing_in_progress';
    public const STATE_SIGNED = 'signed';
    public const STATE_ARCHIVED = 'archived';
    public const STATE_REJECTED = 'rejected';

    public function getSignatureState(): string;

    public function setSignatureState(string $state): static;

    public function getDocumentHash(): ?string;

    public function setDocumentHash(?string $documentHash): static;

    public function getSignedByUserId(): ?int;

    public function setSignedByUserId(?int $signedByUserId): static;

    public function getSignedAt(): ?\DateTimeImmutable;

    public function setSignedAt(?\DateTimeImmutable $signedAt): static;
}
