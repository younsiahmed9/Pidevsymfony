<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[UniqueEntity(fields: ['nomTag'], message: 'Ce tag existe déjà.')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_tag', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom_tag', length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du tag est obligatoire.')]
    #[Assert\Length(
        min: 1,
        max: 100,
        minMessage: 'Le tag doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'Le tag ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $nomTag = '';

    #[ORM\ManyToMany(mappedBy: 'tags', targetEntity: Document::class)]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomTag(): string
    {
        return $this->nomTag;
    }

    public function setNomTag(string $nomTag): static
    {
        $this->nomTag = trim($nomTag);

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function __toString(): string
    {
        return $this->nomTag;
    }
}