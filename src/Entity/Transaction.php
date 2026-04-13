<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: "transaction")]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

    #[ORM\Column(type: "decimal", precision: 15, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(type: "string", length: 10, options: ["default" => "TND"])]
    private ?string $devise = "TND";

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: "string", length: 30)]
    private ?string $type = null;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "SUCCESS"])]
    private ?string $statut = "SUCCESS";

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: CarteVirtuelle::class, inversedBy: 'transactionsSource')]
    #[ORM\JoinColumn(name: "carte_source_id", referencedColumnName: "id", nullable: true)]
    private ?CarteVirtuelle $carte_source = null;

    #[ORM\ManyToOne(targetEntity: CarteVirtuelle::class, inversedBy: 'transactionsDest')]
    #[ORM\JoinColumn(name: "carte_dest_id", referencedColumnName: "id", nullable: true)]
    private ?CarteVirtuelle $carte_dest = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getMontant(): ?string { return $this->montant; }
    public function getDevise(): ?string { return $this->devise; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function getType(): ?string { return $this->type; }
    public function getStatut(): ?string { return $this->statut; }
    public function getDescription(): ?string { return $this->description; }
    public function getCarteSource(): ?CarteVirtuelle { return $this->carte_source; }
    public function getCarteDest(): ?CarteVirtuelle { return $this->carte_dest; }

    // Setters
    public function setMontant(string $montant): self { $this->montant = $montant; return $this; }
    public function setDevise(string $devise): self { $this->devise = $devise; return $this; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setCarteSource(?CarteVirtuelle $carte_source): self { $this->carte_source = $carte_source; return $this; }
    public function setCarteDest(?CarteVirtuelle $carte_dest): self { $this->carte_dest = $carte_dest; return $this; }
}