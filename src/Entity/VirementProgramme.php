<?php

namespace App\Entity;

use App\Repository\VirementProgrammeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VirementProgrammeRepository::class)]
#[ORM\Table(name: "virement_programme")]
class VirementProgramme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: CarteVirtuelle::class)]
    #[ORM\JoinColumn(name: "carte_source_id", referencedColumnName: "id", nullable: true)]
    private ?CarteVirtuelle $carte_source = null;

    #[ORM\ManyToOne(targetEntity: CarteVirtuelle::class)]
    #[ORM\JoinColumn(name: "carte_dest_id", referencedColumnName: "id", nullable: true)]
    private ?CarteVirtuelle $carte_dest = null;

    #[ORM\Column(type: "decimal", precision: 15, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(type: "string", length: 10, options: ["default" => "TND"])]
    private ?string $devise = "TND";

    #[ORM\Column(type: "string", length: 255)]
    private ?string $destinataire = null;

    #[ORM\Column(type: "string", length: 30, options: ["default" => "UNE_FOIS"])]
    private ?string $frequence = "UNE_FOIS";

    #[ORM\Column(name: "prochaine_execution", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $prochaine_execution = null;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "PENDING"])]
    private ?string $statut = "PENDING";

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private ?int $attempts = 0;

    #[ORM\Column(type: "boolean", options: ["default" => true])]
    private ?bool $actif = true;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: "error_message", type: "text", nullable: true)]
    private ?string $error_message = null;

    #[ORM\Column(name: "created_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(name: "updated_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(name: "last_executed", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $last_executed = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function getUtilisateur(): ?User { return $this->user; }
    public function getCarteSource(): ?CarteVirtuelle { return $this->carte_source; }
    public function getCarteDest(): ?CarteVirtuelle { return $this->carte_dest; }
    public function getMontant(): ?string { return $this->montant; }
    public function getDevise(): ?string { return $this->devise; }
    public function getDestinataire(): ?string { return $this->destinataire; }
    public function getFrequence(): ?string { return $this->frequence; }
    public function getProchaineExecution(): ?\DateTimeInterface { return $this->prochaine_execution; }
    public function getStatut(): ?string { return $this->statut; }
    public function getAttempts(): ?int { return $this->attempts; }
    public function isActif(): ?bool { return $this->actif; }
    public function getDescription(): ?string { return $this->description; }
    public function getErrorMessage(): ?string { return $this->error_message; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; }
    public function getLastExecuted(): ?\DateTimeInterface { return $this->last_executed; }

    // Setters
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function setUtilisateur(?User $utilisateur): self { $this->user = $utilisateur; return $this; }
    public function setCarteSource(?CarteVirtuelle $carte_source): self { $this->carte_source = $carte_source; return $this; }
    public function setCarteDest(?CarteVirtuelle $carte_dest): self { $this->carte_dest = $carte_dest; return $this; }
    public function setMontant(string $montant): self { $this->montant = $montant; return $this; }
    public function setDevise(string $devise): self { $this->devise = $devise; return $this; }
    public function setDestinataire(string $destinataire): self { $this->destinataire = $destinataire; return $this; }
    public function setFrequence(string $frequence): self { $this->frequence = $frequence; return $this; }
    public function setProchaineExecution(?\DateTimeInterface $prochaine_execution): self { $this->prochaine_execution = $prochaine_execution; return $this; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }
    public function setAttempts(int $attempts): self { $this->attempts = $attempts; return $this; }
    public function setActif(bool $actif): self { $this->actif = $actif; return $this; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setErrorMessage(?string $error_message): self { $this->error_message = $error_message; return $this; }
    public function setCreatedAt(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function setUpdatedAt(\DateTimeInterface $updated_at): self { $this->updated_at = $updated_at; return $this; }
    public function setLastExecuted(?\DateTimeInterface $last_executed): self { $this->last_executed = $last_executed; return $this; }
}