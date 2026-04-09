<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'budget')]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_budget', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'nom_budget', length: 100)]
    #[Assert\NotBlank(message: "Le nom du budget est obligatoire.")]
    #[Assert\Length(min: 3, minMessage: "Le nom doit faire au moins {{ limit }} caractères.")]
    private ?string $nom_budget = null;

    #[ORM\Column(name: 'montant_total', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Regex(pattern: "/^\d+(\.\d+)?$/", message: "Le montant doit être un nombre valide (uniquement des chiffres).")]
    #[Assert\Positive(message: "Le montant doit être supérieur à zéro.")]
    private ?string $montant_total = null;

    #[ORM\Column(length: 20, nullable: true, options: ['default' => 'mensuel'])]
    private ?string $periode = 'mensuel';

    #[ORM\Column(length: 20, nullable: true, options: ['default' => 'actif'])]
    private ?string $statut = 'actif';

    #[ORM\Column(name: 'date_creation', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_creation = null;

    public function __construct()
    {
        $this->date_creation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getNomBudget(): ?string
    {
        return $this->nom_budget;
    }

    public function setNomBudget(?string $nom_budget): static
    {
        $this->nom_budget = $nom_budget;
        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montant_total;
    }

    public function setMontantTotal(?string $montant_total): static
    {
        $this->montant_total = $montant_total;
        return $this;
    }

    public function getPeriode(): ?string
    {
        return $this->periode;
    }

    public function setPeriode(?string $periode): static
    {
        $this->periode = $periode;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(?\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;
        return $this;
    }
}
