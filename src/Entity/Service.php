<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
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
    private ?string $nomService = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Tarif obligatoire")]
    #[Assert\Positive(message: "Tarif doit être positif")]
    private ?string $tarif = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le type est obligatoire")]
    #[Assert\Choice(
        choices: ["abonnement", "facture"],
        message: "Type invalide"
    )]
    private ?string $typeService = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ["mensuel", "annuel"],
        message: "Fréquence invalide"
    )]
    private ?string $frequence = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\Type(type: "\DateTimeInterface", message: "Date invalide")]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\Type(type: "\DateTimeInterface", message: "Date invalide")]
    #[Assert\Expression(
        "this.getDateDebut() == null || value == null || value > this.getDateDebut()",
        message: "La date de fin doit être après la date de début"
    )]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ["actif", "suspendu", "expire"],
        message: "Statut invalide"
    )]
    private string $statut = 'actif';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDeleted = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomService(): ?string
    {
        return $this->nomService;
    }

    public function setNomService(string $nomService): static
    {
        $this->nomService = $nomService;
        return $this;
    }

    public function getTarif(): ?string
    {
        return $this->tarif;
    }

    public function setTarif(string $tarif): static
    {
        $this->tarif = $tarif;
        return $this;
    }

    public function getTypeService(): ?string
    {
        return $this->typeService;
    }

    public function setTypeService(string $typeService): static
    {
        $this->typeService = $typeService;
        return $this;
    }

    public function getFrequence(): ?string
    {
        return $this->frequence;
    }

    public function setFrequence(?string $frequence): static
    {
        $this->frequence = $frequence;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
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

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }

    public function calculateDuration(): ?int
    {
        if ($this->dateDebut && $this->dateFin) {
            return $this->dateDebut->diff($this->dateFin)->days;
        }
        return null;
    }
}
