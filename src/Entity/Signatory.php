<?php

namespace App\Entity;

use App\Repository\SignatoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignatoryRepository::class)]
#[ORM\Table(name: 'signatory')]
class Signatory
{
    public const ROLE_APPROVER = 'approver';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_CC = 'cc';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_signatory', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'signatories')]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id_document', nullable: false)]
    private ?Document $document = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'email', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'signing_order', type: 'integer')]
    private int $signingOrder = 1;

    #[ORM\Column(name: 'role', length: 50, options: ['default' => 'approver'])]
    private string $role = self::ROLE_APPROVER;

    #[ORM\Column(name: 'status', length: 50, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(name: 'invited_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $invitedAt = null;

    #[ORM\Column(name: 'notified_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    #[ORM\Column(name: 'signed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $signedAt = null;

    #[ORM\Column(name: 'reminder_count', type: 'integer')]
    private int $reminderCount = 0;

    public function __construct()
    {
        $this->invitedAt = new \DateTimeImmutable();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
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

    public function getInvitedAt(): ?\DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function setInvitedAt(?\DateTimeImmutable $invitedAt): static
    {
        $this->invitedAt = $invitedAt;
        return $this;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): static
    {
        $this->notifiedAt = $notifiedAt;
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

    public function getReminderCount(): int
    {
        return $this->reminderCount;
    }

    public function setReminderCount(int $reminderCount): static
    {
        $this->reminderCount = $reminderCount;
        return $this;
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    public function incrementReminderCount(): static
    {
        ++$this->reminderCount;
        return $this;
    }
}