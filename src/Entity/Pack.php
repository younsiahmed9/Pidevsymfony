<?php

namespace App\Entity;

use App\Repository\PackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PackRepository::class)]
#[ORM\Table(name: 'document_bundle')]
class Pack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_bundle', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: false)]
    private ?User $utilisateur = null;

    #[ORM\Column(name: 'nom_bundle', length: 255)]
    #[Assert\NotBlank(message: 'Le nom du pack est obligatoire.')]
    private string $nomPack = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'token_partage', length: 255, unique: true)]
    private string $tokenPartage = '';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\ManyToMany(targetEntity: Document::class, inversedBy: 'packs')]
    #[ORM\JoinTable(name: 'document_bundle_relation')]
    #[ORM\JoinColumn(name: 'bundle_id', referencedColumnName: 'id_bundle')]
    #[ORM\InverseJoinColumn(name: 'document_id', referencedColumnName: 'id_document')]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->tokenPartage = bin2hex(random_bytes(32));
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): ?User { return $this->utilisateur; }
    public function setUtilisateur(?User $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }

    public function getNomPack(): string { return $this->nomPack; }
    public function setNomPack(string $nomPack): static { $this->nomPack = $nomPack; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getTokenPartage(): string { return $this->tokenPartage; }
    public function setTokenPartage(string $tokenPartage): static { $this->tokenPartage = $tokenPartage; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection { return $this->documents; }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
        }
        return $this;
    }

    public function removeDocument(Document $document): static
    {
        $this->documents->removeElement($document);
        return $this;
    }

    public function __toString(): string
    {
        return $this->nomPack;
    }
}
