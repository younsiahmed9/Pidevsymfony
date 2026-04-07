<?php
namespace App\Entity;

use App\Repository\PortefeuilleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortefeuilleRepository::class)]
#[ORM\Table(name: "portefeuille")]
class Portefeuille
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private ?string $nom = null;

    #[ORM\Column(name: "solde_total", type: "decimal", precision: 15, scale: 2, options: ["default" => 0])]
    private ?string $solde_total = "0.00";

    #[ORM\Column(name: "devise_principale", type: "string", length: 10, options: ["default" => "TND"])]
    private ?string $devise_principale = "TND";

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "portefeuilles")]
    #[ORM\JoinColumn(name: "utilisateur_id", nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\OneToMany(mappedBy: "portefeuille", targetEntity: CarteVirtuelle::class, cascade: ["persist", "remove"])]
    private Collection $cartes;

    #[ORM\Column(name: "created_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(name: "updated_at", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $updated_at = null;

    public function __construct()
    {
        $this->cartes = new ArrayCollection();
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function getSoldeTotal(): ?string { return $this->solde_total; }
    public function getDevisePrincipale(): ?string { return $this->devise_principale; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; }

    // Setters
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function setSoldeTotal(string $solde_total): self { $this->solde_total = $solde_total; return $this; }
    public function setDevisePrincipale(string $devise_principale): self { $this->devise_principale = $devise_principale; return $this; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }
    public function setCreatedAt(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function setUpdatedAt(\DateTimeInterface $updated_at): self { $this->updated_at = $updated_at; return $this; }

    // Collection
    public function getCartes(): Collection { return $this->cartes; }
    public function addCarte(CarteVirtuelle $carte): self { if (!$this->cartes->contains($carte)) { $this->cartes->add($carte); $carte->setPortefeuille($this); } return $this; }
    public function removeCarte(CarteVirtuelle $carte): self { $this->cartes->removeElement($carte); return $this; }
}