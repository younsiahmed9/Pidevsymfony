<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'service')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_service', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'nom_service', length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 3, max: 100, minMessage: 'Minimum 3 caractères', maxMessage: 'Maximum 100 caractères')]
    private ?string $nomService = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank(message: 'Tarif obligatoire')]
    #[Assert\Positive(message: 'Tarif doit être positif')]
    private ?string $tarif = null;

    #[ORM\Column(name: 'type_service', type: 'string', columnDefinition: "ENUM('abonnement', 'facture') NOT NULL")]
    #[Assert\NotBlank(message: 'Le type de service est obligatoire')]
    private ?string $typeService = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $frequence = null;

    #[ORM\Column(name: 'date_debut', type: 'datetime')]
    #[Assert\Type(type: '\DateTimeInterface', message: 'Date invalide')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: 'datetime', nullable: true)]
    #[Assert\Type(type: '\DateTimeInterface', message: 'Date invalide')]
    #[Assert\Expression(
        "this.getDateFin() == null or this.getDateFin() >= this.getDateDebut()",
        message: 'La date de fin doit être après la date de début'
    )]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 20, type: 'string', columnDefinition: "ENUM('actif', 'suspendu', 'expire') NOT NULL DEFAULT 'actif'")]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    private string $statut = 'actif';

    /** @var Collection<int, Facture> */
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Facture::class)]
    private Collection $factures;

    public function __construct()
    {
        $this->factures = new ArrayCollection();
        $this->dateDebut = new \DateTime();
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

    public function getNomService(): ?string
    {
        return $this->nomService;
    }

    public function setNomService(string $nomService): static
    {
        $this->nomService = $nomService;

        return $this;
    }

    public function getTarif(): ?string
    {
        return $this->tarif;
    }

    public function setTarif(string $tarif): static
    {
        $this->tarif = $tarif;

        return $this;
    }

    public function getTypeService(): ?string
    {
        return $this->typeService;
    }

    public function setTypeService(?string $typeService): static
    {
        $this->typeService = $typeService;

        return $this;
    }

    public function getFrequence(): ?string
    {
        return $this->frequence;
    }

    public function setFrequence(?string $frequence): static
    {
        $this->frequence = $frequence;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

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

    /** @return Collection<int, Facture> */
    public function getFactures(): Collection
    {
        return $this->factures;
    }

    public function __toString(): string
    {
        return $this->nomService ?? '';
    }
}
