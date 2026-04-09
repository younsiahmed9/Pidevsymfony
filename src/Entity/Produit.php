<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[UniqueEntity(fields: ['code_unique'], message: 'Ce code existe déjà')]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: "Minimum 3 caractères",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $nomProduit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Montant obligatoire")]
    #[Assert\Positive(message: "Montant positif")]
    #[Assert\Range(
        min: 1,
        max: 100000,
        notInRangeMessage: "Entre 1 et 100000 DT"
    )]
    private ?string $montant = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: "Code obligatoire")]
    #[Assert\Regex(
        pattern: "/^[A-Z0-9]{4,20}$/",
        message: "Uniquement majuscules et chiffres"
    )]
    private ?string $codeUnique = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le type est obligatoire")]
    #[Assert\Choice(
        choices: ["carte_cadeau", "carte_abonnement", "carte_prepayee"],
        message: "Type invalide"
    )]
    private ?string $typeProduit = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ["disponible", "vendu", "expire"],
        message: "Statut invalide"
    )]
    private ?string $statut = "disponible";

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDeleted = false;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setCodeUnique(string $codeUnique): static
    {
        $this->codeUnique = $codeUnique;
        return $this;
    }

    public function getTypeProduit(): ?string
    {
        return $this->typeProduit;
    }

    public function setTypeProduit(string $typeProduit): static
    {
        $this->typeProduit = $typeProduit;
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

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
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
