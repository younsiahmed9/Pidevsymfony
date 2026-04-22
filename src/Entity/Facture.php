<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
#[ORM\Table(name: 'facture')]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_facture', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(name: 'date_facture', type: 'date')]
    private ?\DateTimeInterface $dateFacture = null;

    #[ORM\Column(name: 'date_echeance', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'factures')]
    #[ORM\JoinColumn(name: 'id_service', referencedColumnName: 'id_service', nullable: true)]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: Produit::class, inversedBy: 'factures')]
    #[ORM\JoinColumn(name: 'id_produit', referencedColumnName: 'id_produit', nullable: true)]
    private ?Produit $produit = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'non_payee';

    #[ORM\Column(name: 'numero_facture', length: 50, nullable: true)]
    private ?string $numeroFacture = null;

    public function __construct()
    {
        $this->dateFacture = new \DateTime();
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

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getDateFacture(): ?\DateTimeInterface
    {
        return $this->dateFacture;
    }

    public function setDateFacture(?\DateTimeInterface $dateFacture): static
    {
        $this->dateFacture = $dateFacture;

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeInterface $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

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

    public function getNumeroFacture(): ?string
    {
        return $this->numeroFacture;
    }

    public function setNumeroFacture(?string $numeroFacture): static
    {
        $this->numeroFacture = $numeroFacture;

        return $this;
    }

    public function __toString(): string
    {
        return $this->numeroFacture ?? ('Facture #' . ($this->id ?? '?'));
    }
}
