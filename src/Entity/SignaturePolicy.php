<?php

namespace App\Entity;

use App\Repository\SignaturePolicyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignaturePolicyRepository::class)]
#[ORM\Table(name: 'signature_policy')]
class SignaturePolicy
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_ADVANCED = 'advanced';
    public const TYPE_QUALIFIED = 'qualified';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_signature_policy', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 100)]
    private string $name = '';

    #[ORM\Column(name: 'type', length: 50)]
    private string $type = self::TYPE_SIMPLE;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'requires_human_validation', type: 'boolean')]
    private bool $requiresHumanValidation = false;

    #[ORM\Column(name: 'require_2fa', type: 'boolean')]
    private bool $require2fa = false;

    #[ORM\Column(name: 'provider', length: 100, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(name: 'is_default', type: 'boolean')]
    private bool $isDefault = false;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'config', type: 'json', nullable: true)]
    private ?array $config = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function requiresHumanValidation(): bool
    {
        return $this->requiresHumanValidation;
    }

    public function setRequiresHumanValidation(bool $requiresHumanValidation): static
    {
        $this->requiresHumanValidation = $requiresHumanValidation;
        return $this;
    }

    public function requires2fa(): bool
    {
        return $this->require2fa;
    }

    public function setRequire2fa(bool $require2fa): static
    {
        $this->require2fa = $require2fa;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;
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
}