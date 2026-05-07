<?php

namespace App\Entity;

use App\Repository\ReleveRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReleveRepository::class)]
#[ORM\Table(name: 'releve')]
#[ORM\Index(columns: ['compte_id'], name: 'idx_releve_compte')]
#[ORM\Index(columns: ['date_generation'], name: 'idx_releve_date')]
class Releve
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Compte::class, inversedBy: 'releves')]
    #[ORM\JoinColumn(name: 'compte_id', referencedColumnName: 'id_compte', nullable: false, onDelete: 'CASCADE')]
    private ?Compte $compte = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateGeneration = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $cheminFichier = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $soldeAuMoment = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $nombreCreditsAuMoment = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $typeReleve = 'compte_complet'; // compte_complet, credits_seulement

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metadonnees = null; // JSON avec données additionnelles

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompte(): ?Compte
    {
        return $this->compte;
    }

    public function setCompte(?Compte $compte): self
    {
        $this->compte = $compte;
        return $this;
    }

    public function getDateGeneration(): ?\DateTimeImmutable
    {
        return $this->dateGeneration;
    }

    public function setDateGeneration(\DateTimeImmutable $dateGeneration): self
    {
        $this->dateGeneration = $dateGeneration;
        return $this;
    }

    public function getCheminFichier(): ?string
    {
        return $this->cheminFichier;
    }

    public function setCheminFichier(string $cheminFichier): self
    {
        $this->cheminFichier = $cheminFichier;
        return $this;
    }

    public function getSoldeAuMoment(): ?string
    {
        return $this->soldeAuMoment;
    }

    public function setSoldeAuMoment(string $soldeAuMoment): self
    {
        $this->soldeAuMoment = $soldeAuMoment;
        return $this;
    }

    public function getNombreCreditsAuMoment(): ?int
    {
        return $this->nombreCreditsAuMoment;
    }

    public function setNombreCreditsAuMoment(int $nombreCreditsAuMoment): self
    {
        $this->nombreCreditsAuMoment = $nombreCreditsAuMoment;
        return $this;
    }

    public function getTypeReleve(): ?string
    {
        return $this->typeReleve;
    }

    public function setTypeReleve(string $typeReleve): self
    {
        $this->typeReleve = $typeReleve;
        return $this;
    }

    public function getMetadonnees(): ?string
    {
        return $this->metadonnees;
    }

    public function setMetadonnees(?string $metadonnees): self
    {
        $this->metadonnees = $metadonnees;
        return $this;
    }

    public function getMetadonneesArray(): array
    {
        return $this->metadonnees ? json_decode($this->metadonnees, true) : [];
    }

    public function setMetadonneesArray(array $data): self
    {
        $this->metadonnees = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $this;
    }
}