<?php

namespace App\Entity;

use App\Repository\DepenseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
#[ORM\Table(name: 'depense')]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_depense', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Budget::class, inversedBy: 'depenses')]
    #[ORM\JoinColumn(name: 'id_budget', referencedColumnName: 'id_budget', nullable: true)]
    private ?Budget $budget = null;

    #[ORM\Column(length: 100)]
    private ?string $categorie = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(name: 'date_depense', type: 'datetime')]
    private ?\DateTimeInterface $dateDepense = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'mode_paiement', length: 30)]
    private ?string $modePaiement = null;

    public function __construct()
    {
        $this->dateDepense = new \DateTime();
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

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

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

    public function setModePaiement(string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;

        return $this;
    }
}
