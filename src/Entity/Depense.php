<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'depense')]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_depense', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Budget::class)]
    #[ORM\JoinColumn(name: 'id_budget', referencedColumnName: 'id_budget', nullable: true)]
    private ?Budget $budget = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "La catégorie est obligatoire.")]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Regex(pattern: "/^\d+(\.\d+)?$/", message: "Le montant doit être un nombre valide (uniquement des chiffres).")]
    #[Assert\Positive(message: "Le montant doit être supérieur à zéro.")]
    private ?string $montant = null;

    #[ORM\Column(name: 'date_depense', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateDepense = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'mode_paiement', length: 20, type: 'string', options: ['default' => 'carte'])]
    private ?string $modePaiement = 'carte';

    public function __construct()
    {
        $this->dateDepense = new \DateTime();
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

    public function getBudget(): ?Budget
    {
        return $this->budget;
    }

    public function setBudget(?Budget $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(?string $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function getDateDepense(): ?\DateTimeInterface
    {
        return $this->dateDepense;
    }

    public function setDateDepense(?\DateTimeInterface $dateDepense): static
    {
        $this->dateDepense = $dateDepense;
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

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }
}
