<?php
namespace App\Entity;
use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_document', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un utilisateur.')]
    private ?User $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'id_categorie', referencedColumnName: 'id_categorie', nullable: true)]
    #[Assert\NotNull(message: 'Veuillez sélectionner une catégorie.')]
    private ?Categorie $categorie = null;

    #[ORM\ManyToOne(targetEntity: Dossier::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'id_dossier', referencedColumnName: 'id_dossier', nullable: true)]
    private ?Dossier $dossier = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre du document est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $titre = '';

    #[ORM\Column(name: 'type_document', length: 100)]
    #[Assert\NotBlank(message: 'Le type de document est obligatoire.')]
    #[Assert\Choice(
        choices: ['contrat', 'facture', 'releve', 'identite', 'assurance', 'fiscal', 'autre'],
        message: 'Veuillez choisir un type de document valide.'
    )]
    private string $typeDocument = '';

    #[ORM\Column(name: 'chemin_fichier', length: 500)]
    #[Assert\NotBlank(message: 'Le fichier du document est obligatoire.')]
    private string $cheminFichier = '';

    #[ORM\Column(name: 'taille_fichier', type: 'integer', nullable: true)]
    private ?int $tailleFichier = null;

    #[ORM\Column(name: 'date_document', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateDocument = null;

    #[ORM\Column(name: 'date_echeance', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: 'string', columnDefinition: "ENUM('valide','expire','a_renouveler','archive') NOT NULL DEFAULT 'valide'")]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['valide', 'expire', 'a_renouveler', 'archive'],
        message: 'Veuillez choisir un statut valide.'
    )]
    private string $statut = 'valide';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Les tags ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $tags = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: Echeance::class, cascade: ['persist', 'remove'])]
    private Collection $echeances;

    public function __construct()
    {
        $this->echeances = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?User { return $this->utilisateur; }
    public function setUtilisateur(?User $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }
    public function getCategorie(): ?Categorie { return $this->categorie; }
    public function setCategorie(?Categorie $categorie): static { $this->categorie = $categorie; return $this; }
    public function getDossier(): ?Dossier { return $this->dossier; }
    public function setDossier(?Dossier $dossier): static { $this->dossier = $dossier; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getTypeDocument(): string { return $this->typeDocument; }
    public function setTypeDocument(string $typeDocument): static { $this->typeDocument = $typeDocument; return $this; }
    public function getCheminFichier(): string { return $this->cheminFichier; }
    public function setCheminFichier(string $cheminFichier): static { $this->cheminFichier = $cheminFichier; return $this; }
    public function getTailleFichier(): ?int { return $this->tailleFichier; }
    public function setTailleFichier(?int $tailleFichier): static { $this->tailleFichier = $tailleFichier; return $this; }
    public function getDateDocument(): ?\DateTimeInterface { return $this->dateDocument; }
    public function setDateDocument(?\DateTimeInterface $dateDocument): static { $this->dateDocument = $dateDocument; return $this; }
    public function getDateEcheance(): ?\DateTimeInterface { return $this->dateEcheance; }
    public function setDateEcheance(?\DateTimeInterface $dateEcheance): static { $this->dateEcheance = $dateEcheance; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getTags(): ?string { return $this->tags; }
    public function setTags(?string $tags): static { $this->tags = $tags; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    public function getEcheances(): Collection { return $this->echeances; }
    public function __toString(): string { return $this->titre; }
}
