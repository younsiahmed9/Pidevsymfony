<?php
namespace App\Entity;
use App\Repository\CreditRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CreditRepository::class)]
#[ORM\Table(name: 'credit')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_credit', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Compte::class, inversedBy: 'credits')]
    #[ORM\JoinColumn(name: 'id_compte', referencedColumnName: 'id_compte', nullable: false)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un compte.')]
    private Compte $compte;

    #[ORM\Column(name: 'montant', type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Assert\GreaterThan(value: 0, message: 'Le montant doit être supérieur à 0.')]
    private string $montant;

    #[ORM\Column(name: 'taux_interet', type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Le taux d\'intérêt est obligatoire.')]
    #[Assert\Range(min: 0.01, max: 30, notInRangeMessage: 'Le taux doit être entre {{ min }}% et {{ max }}%.')]
    private string $tauxInteret;

    #[ORM\Column(name: 'duree_mois', type: 'integer')]
    #[Assert\NotBlank(message: 'La durée est obligatoire.')]
    #[Assert\Range(min: 1, max: 360, notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} mois.')]
    private int $dureeMois;

    #[ORM\Column(name: 'mensualite', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $mensualite = null;

    #[ORM\Column(name: 'date_debut', type: 'date')]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(name: 'status', length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    private string $status = 'en_attente';

    public function __construct()
    {
        $this->dateDebut = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getCompte(): Compte { return $this->compte; }
    public function setCompte(Compte $compte): static { $this->compte = $compte; return $this; }
    public function getMontant(): string { return $this->montant; }
    public function setMontant(string $montant): static { $this->montant = $montant; return $this; }
    public function getTauxInteret(): string { return $this->tauxInteret; }
    public function setTauxInteret(string $tauxInteret): static { $this->tauxInteret = $tauxInteret; return $this; }
    public function getDureeMois(): int { return $this->dureeMois; }
    public function setDureeMois(int $dureeMois): static { $this->dureeMois = $dureeMois; return $this; }
    public function getMensualite(): ?string { return $this->mensualite; }
    public function setMensualite(?string $mensualite): static { $this->mensualite = $mensualite; return $this; }
    public function getDateDebut(): \DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function __toString(): string { return 'Crédit #' . ($this->id ?? '?') . ' - ' . ($this->montant ?? '0') . ' TND'; }
}
