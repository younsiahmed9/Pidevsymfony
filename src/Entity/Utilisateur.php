<?php
namespace App\Entity;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(type: 'string', columnDefinition: "ENUM('admin','user') NOT NULL DEFAULT 'user'")]
    private string $role = 'user';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $solde = '0.00';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Compte::class, cascade: ['persist', 'remove'])]
    private Collection $comptes;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Document::class, cascade: ['persist', 'remove'])]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Dossier::class, cascade: ['persist', 'remove'])]
    private Collection $dossiers;

    public function __construct()
    {
        $this->comptes = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->dossiers = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }
    public function getSolde(): string { return $this->solde; }
    public function setSolde(string $solde): static { $this->solde = $solde; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    public function getComptes(): Collection { return $this->comptes; }
    public function getDocuments(): Collection { return $this->documents; }
    public function getDossiers(): Collection { return $this->dossiers; }
    public function __toString(): string { return $this->prenom . ' ' . $this->nom; }
}
