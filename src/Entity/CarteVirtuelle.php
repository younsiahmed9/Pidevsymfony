<?php

namespace App\Entity;

use App\Repository\CarteVirtuelleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CarteVirtuelleRepository::class)]
#[ORM\Table(name: "carte_virtuelle")]
class CarteVirtuelle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

    #[ORM\Column(name: "numero_carte", type: "string", length: 32, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro de carte est obligatoire.')]
    private ?string $numero_carte = '';

    #[ORM\Column(type: "string", length: 8, nullable: true)]
    private ?string $cvv = null;

    #[ORM\Column(name: "date_expiration", type: "date", nullable: true)]
    private ?\DateTimeInterface $date_expiration = null;

    #[ORM\Column(type: "decimal", precision: 15, scale: 2, options: ["default" => 0])]
    private ?string $solde = "0.00";

    #[ORM\Column(type: "decimal", precision: 15, scale: 2, options: ["default" => 1000])]
    #[Assert\NotBlank(message: 'Le plafond est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Le plafond doit être un nombre avec au plus 2 décimales.'
    )]
    private ?string $plafond = "1000.00";

    #[ORM\Column(type: "string", length: 20, options: ["default" => "NORMAL"])]
    #[Assert\NotBlank(message: 'Le type de carte est obligatoire.')]
    private ?string $type = "NORMAL";

    #[ORM\Column(type: "string", length: 10, options: ["default" => "TND"])]
    #[Assert\NotBlank(message: 'La devise est obligatoire.')]
    private ?string $devise = "TND";

    #[ORM\ManyToOne(targetEntity: Portefeuille::class, inversedBy: "cartes")]
    #[ORM\JoinColumn(name: "portefeuille_id", nullable: false)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un portefeuille.')]
    private ?Portefeuille $portefeuille = null;

    #[ORM\Column(name: "is_active", type: "boolean", options: ["default" => true])]
    private ?bool $is_active = true;

    #[ORM\Column(name: "created_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(name: "updated_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\OneToMany(mappedBy: "carte_source", targetEntity: Transaction::class)]
    private Collection $transactionsSource;

    #[ORM\OneToMany(mappedBy: "carte_dest", targetEntity: Transaction::class)]
    private Collection $transactionsDest;

    public function __construct()
    {
        $this->transactionsSource = new ArrayCollection();
        $this->transactionsDest = new ArrayCollection();
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNumeroCarte(): ?string { return $this->numero_carte; }
    public function getCvv(): ?string { return $this->cvv; }
    public function getDateExpiration(): ?\DateTimeInterface { return $this->date_expiration; }
    public function getSolde(): ?string { return $this->solde; }
    public function getPlafond(): ?string { return $this->plafond; }
    public function getType(): ?string { return $this->type; }
    public function getDevise(): ?string { return $this->devise; }
    public function getPortefeuille(): ?Portefeuille { return $this->portefeuille; }
    public function isIsActive(): ?bool { return $this->is_active; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; }

    // Setters
    public function setNumeroCarte(string $numero_carte): self { $this->numero_carte = $numero_carte; return $this; }
    public function setCvv(?string $cvv): self { $this->cvv = $cvv; return $this; }
    public function setDateExpiration(?\DateTimeInterface $date_expiration): self { $this->date_expiration = $date_expiration; return $this; }
    public function setSolde(string $solde): self { $this->solde = $solde; return $this; }
    public function setPlafond(string $plafond): self { $this->plafond = $plafond; return $this; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function setDevise(string $devise): self { $this->devise = $devise; return $this; }
    public function setPortefeuille(?Portefeuille $portefeuille): self { $this->portefeuille = $portefeuille; return $this; }
    public function setIsActive(bool $is_active): self { $this->is_active = $is_active; return $this; }
    public function setCreatedAt(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function setUpdatedAt(\DateTimeInterface $updated_at): self { $this->updated_at = $updated_at; return $this; }

    // Collections
    public function getTransactionsSource(): Collection { return $this->transactionsSource; }
    public function getTransactionsDest(): Collection { return $this->transactionsDest; }
}