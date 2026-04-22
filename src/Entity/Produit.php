<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

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
    private ?string $nomProduit = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(name: 'code_unique', length: 100, nullable: true)]
    private ?string $codeUnique = null;

    #[ORM\Column(name: 'type_produit', length: 100, nullable: true)]
    private ?string $typeProduit = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'disponible';

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

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
