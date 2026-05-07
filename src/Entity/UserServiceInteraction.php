<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_service_interaction')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_interactions')]
#[ORM\Index(columns: ['service_id'], name: 'idx_service_interactions')]
class UserServiceInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id_service', nullable: false)]
    private Service $service;

    #[ORM\Column(type: 'string', length: 20)]
    private string $interactionType; // 'view', 'purchase', 'favorite', 'review'

    #[ORM\Column(type: 'float')]
    private float $rating = 0.0; // 0.0 to 5.0

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'integer')]
    private int $frequency = 1; // How many times this interaction occurred

    public function __construct(User $user, Service $service, string $interactionType)
    {
        $this->user = $user;
        $this->service = $service;
        $this->interactionType = $interactionType;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function setService(Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getInteractionType(): string
    {
        return $this->interactionType;
    }

    public function setInteractionType(string $interactionType): static
    {
        $this->interactionType = $interactionType;
        return $this;
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function setRating(float $rating): static
    {
        $this->rating = max(0.0, min(5.0, $rating));
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getFrequency(): int
    {
        return $this->frequency;
    }

    public function setFrequency(int $frequency): static
    {
        $this->frequency = max(1, $frequency);
        return $this;
    }

    public function incrementFrequency(): static
    {
        $this->frequency++;
        return $this;
    }
}
