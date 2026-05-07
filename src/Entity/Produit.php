<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[ORM\Table(name: 'produit')]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_produit', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'nom_produit', length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 3, max: 100)]
    private ?string $nomProduit = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank(message: 'Montant obligatoire')]
    #[Assert\Positive(message: 'Montant positif')]
    #[Assert\Range(min: 1, max: 100000, notInRangeMessage: 'Le montant doit être entre 1 DT et 100000 DT')]
    private ?string $montant = null;

    #[ORM\Column(name: 'code_unique', length: 100, nullable: true, unique: true)]
    #[Assert\Length(min: 4, max: 20, minMessage: 'Minimum 4 caractères', maxMessage: 'Maximum 20 caractères')]
    #[Assert\Unique(message: 'Ce code existe déjà')]
    private ?string $codeUnique = null;

    #[ORM\Column(name: 'type_produit', type: 'string', columnDefinition: "ENUM('carte_prepaye', 'carte_cadeaux', 'carte_abonnement') NOT NULL")]
    #[Assert\NotBlank(message: 'Le type de produit est obligatoire')]
    private ?string $typeProduit = null;

    #[ORM\Column(length: 20, type: 'string', columnDefinition: "ENUM('vendu', 'expire', 'disponible') NOT NULL DEFAULT 'disponible'")]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    private string $statut = 'disponible';

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTime $dateCreation = null;

    /** @var Collection<int, Facture> */
    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: Facture::class)]
    private Collection $factures;

    public function __construct()
    {
        $this->factures = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNomProduit(): ?string
    {
        return $this->nomProduit;
    }

    public function setNomProduit(string $nomProduit): static
    {
        $this->nomProduit = $nomProduit;

        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getCodeUnique(): ?string
    {
        return $this->codeUnique;
    }

    public function setCodeUnique(?string $codeUnique): static
    {
        $this->codeUnique = $codeUnique;

        return $this;
    }

    public function getTypeProduit(): ?string
    {
        return $this->typeProduit;
    }

    public function setTypeProduit(?string $typeProduit): static
    {
        $this->typeProduit = $typeProduit;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /** @return Collection<int, Facture> */
    public function getFactures(): Collection
    {
        return $this->factures;
    }

    public function __toString(): string
    {
        return $this->nomProduit ?? '';
    }
}
