<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Validator\RequireServiceOrProduit;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
#[UniqueEntity(fields: ['numero_facture'], message: 'Ce numéro de facture existe déjà !')]
#[ORM\HasLifecycleCallbacks]
#[RequireServiceOrProduit]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Le montant est obligatoire")]
    #[Assert\Positive(message: "Le montant doit être positif")]
    private ?string $montant = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "La date de facture est obligatoire")]
    private ?\DateTimeInterface $dateFacture = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "La date d'échéance est obligatoire")]
    #[Assert\GreaterThan(propertyPath: "dateFacture", message: "La date d'échéance doit être après la date de facture")]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'id_service', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(name: 'id_produit', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Produit $produit = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(
        choices: ["en_attente", "payee", "impayee"],
        message: "Statut invalide"
    )]
    private string $statut = 'en_attente';

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: "Le numéro de facture est obligatoire")]
    #[Assert\Regex(
        pattern: "/^FAC-\d{4}-\d{2}-\d{2}-\d{3}$/",
        message: "Format invalide (FAC-AAAA-MM-JJ-XXX)"
    )]
    private ?string $numeroFacture = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDeleted = false;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateCalculatedFields(): void
    {
        // 1. Calcul du montant
        if ($this->produit) {
            $this->montant = $this->produit->getMontant();
        } elseif ($this->service) {
            $this->montant = $this->service->getTarif();
        }

        // 2. Calcul de la date d'échéance (+30 jours)
        if ($this->dateFacture) {
            $echeance = clone $this->dateFacture;
            $echeance->modify('+30 days');
            $this->dateEcheance = $echeance;
        }
    }

    public function __construct()
    {
        $this->dateFacture = new \DateTime();
    }

    public function isExpired(): bool
    {
        if (!$this->dateEcheance) {
            return false;
        }
        return $this->dateEcheance < new \DateTime() && $this->statut === 'en_attente';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setDateFacture(\DateTimeInterface $dateFacture): static
    {
        $this->dateFacture = $dateFacture;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(\DateTimeInterface $dateEcheance): static
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

    public function getStatut(): ?string
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

    public function setNumeroFacture(string $numeroFacture): static
    {
        $this->numeroFacture = $numeroFacture;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }
}
