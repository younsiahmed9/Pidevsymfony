<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 190, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $profilePhoto = null;

    #[ORM\Column(type: Types::BLOB, nullable: true, columnDefinition: 'MEDIUMBLOB DEFAULT NULL')]
    private $fingerprintTemplate = null;

    #[ORM\Column(type: Types::BLOB, nullable: true, columnDefinition: 'MEDIUMBLOB DEFAULT NULL')]
    private $faceTemplate = null;

    #[ORM\Column(type: Types::STRING, length: 20, columnDefinition: "ENUM('ADMIN','CLIENT') NOT NULL")]
    private ?string $role = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, columnDefinition: 'TIMESTAMP NOT NULL')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, columnDefinition: 'TIMESTAMP NOT NULL')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'], targetEntity: Admin::class)]
    private ?Admin $admin = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'], targetEntity: Client::class)]
    private ?Client $client = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getProfilePhoto(): ?string
    {
        return $this->profilePhoto;
    }

    public function setProfilePhoto(?string $profilePhoto): static
    {
        $this->profilePhoto = $profilePhoto;

        return $this;
    }

    public function getFingerprintTemplate()
    {
        return $this->fingerprintTemplate;
    }

    public function setFingerprintTemplate($fingerprintTemplate): static
    {
        $this->fingerprintTemplate = $fingerprintTemplate;

        return $this;
    }

    public function getFaceTemplate(): mixed
    {
        return $this->faceTemplate;
    }

    public function setFaceTemplate(mixed $faceTemplate): static
    {
        $this->faceTemplate = $faceTemplate;

        return $this;
    }

    public function getFaceTemplateAsString(): ?string
    {
        $faceTemplate = $this->faceTemplate;

        if ($faceTemplate === null) {
            return null;
        }

        if (is_resource($faceTemplate)) {
            @rewind($faceTemplate);
            $contents = stream_get_contents($faceTemplate);
            return $contents !== false && $contents !== '' ? $contents : null;
        }

        $faceTemplateString = trim((string) $faceTemplate);
        return $faceTemplateString !== '' ? $faceTemplateString : null;
    }

    public function getFaceDescriptor(): array
    {
        $faceTemplateString = $this->getFaceTemplateAsString();
        if ($faceTemplateString === null) {
            return [];
        }

        $decoded = json_decode($faceTemplateString, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static fn ($value) => (float) $value, $decoded));
    }

    public function hasFaceTemplate(): bool
    {
        return $this->getFaceTemplateAsString() !== null;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function setAdmin(?Admin $admin): static
    {
        if ($admin === null && $this->admin !== null) {
            $this->admin->setUser(null);
        } else if ($admin !== null && $admin->getUser() !== $this) {
            $admin->setUser($this);
        }

        $this->admin = $admin;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        if ($client === null && $this->client !== null) {
            $this->client->setUser(null);
        } else if ($client !== null && $client->getUser() !== $this) {
            $client->setUser($this);
        }

        $this->client = $client;

        return $this;
    }

    // UserInterface methods
    public function getRoles(): array
    {
        $roles = [];
        
        if ($this->role === 'ADMIN') {
            $roles[] = 'ROLE_ADMIN';
        } elseif ($this->role === 'CLIENT') {
            $roles[] = 'ROLE_CLIENT';
        }
        
        $roles[] = 'ROLE_USER';
        
        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // Erase any sensitive data if needed
    }

    public function getUserIdentifier(): string
    {
        return (string)$this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }
}
