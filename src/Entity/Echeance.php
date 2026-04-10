<?php
namespace App\Entity;
use App\Repository\EcheanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EcheanceRepository::class)]
#[ORM\Table(name: 'echeance')]
class Echeance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_echeance', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'echeances')]
    #[ORM\JoinColumn(name: 'id_document', referencedColumnName: 'id_document', nullable: false)]
    private Document $document;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id', nullable: false)]
    private Utilisateur $utilisateur;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(name: 'date_echeance', type: 'date')]
    private \DateTimeInterface $dateEcheance;

    #[ORM\Column(name: 'date_rappel', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateRappel = null;

    #[ORM\Column(type: 'string', columnDefinition: "ENUM('pending','notified','completed','overdue') NOT NULL DEFAULT 'pending'")]
    private string $statut = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'montant', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $montant = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getDocument(): Document { return $this->document; }
    public function setDocument(Document $document): static { $this->document = $document; return $this; }
    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDateEcheance(): \DateTimeInterface { return $this->dateEcheance; }
    public function setDateEcheance(\DateTimeInterface $dateEcheance): static { $this->dateEcheance = $dateEcheance; return $this; }
    public function getDateRappel(): ?\DateTimeInterface { return $this->dateRappel; }
    public function setDateRappel(?\DateTimeInterface $dateRappel): static { $this->dateRappel = $dateRappel; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(?string $montant): static { $this->montant = $montant; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
