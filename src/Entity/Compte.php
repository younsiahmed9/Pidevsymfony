<?php
namespace App\Entity;
use App\Repository\CompteRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CompteRepository::class)]
#[ORM\Table(name: 'compte')]
#[UniqueEntity(fields: ['numeroCompte'], message: 'Ce numéro de compte existe déjà.')]
class Compte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_compte', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un utilisateur.')]
    private User $utilisateur;

    #[ORM\Column(name: 'numero_compte', length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro de compte est obligatoire.')]
    #[Assert\Length(min: 3, max: 50, minMessage: 'Minimum {{ limit }} caractères.', maxMessage: 'Maximum {{ limit }} caractères.')]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9\- ]+$/',
        message: 'Le numéro de compte ne peut contenir que des lettres, chiffres, espaces ou tirets.'
    )]
    private string $numeroCompte;

    #[ORM\Column(name: 'type_compte', length: 50)]
    #[Assert\NotBlank(message: 'Le type de compte est obligatoire.')]
    #[Assert\Choice(choices: ['courant', 'epargne'], message: 'Type invalide.')]
    private string $typeCompte;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank(message: 'Le solde est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Le solde doit être un nombre positif avec maximum 2 décimales.'
    )]
    private string $solde = '0.00';

    #[ORM\Column(name: 'taux_interet', type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Le taux doit être entre {{ min }}% et {{ max }}%.')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Le taux d\'intérêt doit être numérique et comporter au maximum 2 décimales.'
    )]
    private ?string $tauxInteret = null;

    #[ORM\Column(name: 'plafond_decouvert', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le plafond doit être positif ou nul.')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Le plafond découvert doit être numérique et comporter au maximum 2 décimales.'
    )]
    private ?string $plafondDecouvert = null;

    #[ORM\Column(name: 'date_creation', type: 'date')]
    #[Assert\NotNull(message: 'La date de création est obligatoire.')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'L\'état est obligatoire.')]
    #[Assert\Choice(choices: ['actif', 'bloque', 'clos'], message: 'État invalide.')]
    private string $etat = 'actif';

    #[ORM\OneToMany(mappedBy: 'compte', targetEntity: Credit::class, cascade: ['persist', 'remove'])]
    private Collection $credits;

    public function __construct()
    {
        $this->credits = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    #[Assert\Callback]
    public function validateTypeSpecificFields(ExecutionContextInterface $context): void
    {
        if ($this->typeCompte === 'courant') {
            if ($this->plafondDecouvert === null || $this->plafondDecouvert === '') {
                $context->buildViolation('Le plafond découvert est obligatoire pour un compte courant.')
                    ->atPath('plafondDecouvert')
                    ->addViolation();
            }

            if ($this->tauxInteret !== null && $this->tauxInteret !== '') {
                $context->buildViolation('Le taux d\'intérêt n\'est pas applicable pour un compte courant.')
                    ->atPath('tauxInteret')
                    ->addViolation();
            }
        }

        if ($this->typeCompte === 'epargne') {
            if ($this->tauxInteret === null || $this->tauxInteret === '') {
                $context->buildViolation('Le taux d\'intérêt est obligatoire pour un compte épargne.')
                    ->atPath('tauxInteret')
                    ->addViolation();
            }

            if ($this->plafondDecouvert !== null && $this->plafondDecouvert !== '') {
                $context->buildViolation('Le plafond découvert n\'est pas applicable pour un compte épargne.')
                    ->atPath('plafondDecouvert')
                    ->addViolation();
            }
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }
    public function getNumeroCompte(): string { return $this->numeroCompte; }
    public function setNumeroCompte(string $numeroCompte): static { $this->numeroCompte = $numeroCompte; return $this; }
    public function getTypeCompte(): string { return $this->typeCompte; }
    public function setTypeCompte(string $typeCompte): static { $this->typeCompte = $typeCompte; return $this; }
    public function getSolde(): string { return $this->solde; }
    public function setSolde(string $solde): static { $this->solde = $solde; return $this; }
    public function getTauxInteret(): ?string { return $this->tauxInteret; }
    public function setTauxInteret(?string $tauxInteret): static { $this->tauxInteret = $tauxInteret; return $this; }
    public function getPlafondDecouvert(): ?string { return $this->plafondDecouvert; }
    public function setPlafondDecouvert(?string $plafondDecouvert): static { $this->plafondDecouvert = $plafondDecouvert; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getEtat(): string { return $this->etat; }
    public function setEtat(string $etat): static { $this->etat = $etat; return $this; }
    public function getCredits(): Collection { return $this->credits; }
    public function __toString(): string { return $this->numeroCompte ?? ''; }
}
