<?php

namespace App\Entity;

use App\Repository\BudgetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\Table(name: 'budget')]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_budget', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'nom_budget', length: 255)]
    private ?string $nomBudget = null;

    #[ORM\Column(name: 'montant_total', type: 'decimal', precision: 15, scale: 2)]
    private ?string $montantTotal = null;

    #[ORM\Column(length: 50)]
    private ?string $periode = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'actif';

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTime $dateCreation = null;

    /** @var Collection<int, Depense> */
    #[ORM\OneToMany(mappedBy: 'budget', targetEntity: Depense::class)]
    private Collection $depenses;

    public function __construct()
    {
        $this->depenses = new ArrayCollection();
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

    public function getNomBudget(): ?string
    {
        return $this->nomBudget;
    }

    public function setNomBudget(string $nomBudget): static
    {
        $this->nomBudget = $nomBudget;

        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;

        return $this;
    }

    public function getPeriode(): ?string
    {
        return $this->periode;
    }

    public function setPeriode(string $periode): static
    {
        $this->periode = $periode;

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

    /** @return Collection<int, Depense> */
    public function getDepenses(): Collection
    {
        return $this->depenses;
    }

    public function __toString(): string
    {
        return $this->nomBudget ?? '';
    }
}
