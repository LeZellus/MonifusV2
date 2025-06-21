<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $community = null;

    /**
     * @var Collection<int, DofusCharacter>
     */
    #[ORM\OneToMany(targetEntity: DofusCharacter::class, mappedBy: 'server', fetch: 'EXTRA_LAZY')]
    private Collection $dofusCharacters;

    public function __construct()
    {
        $this->dofusCharacters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCommunity(): ?string
    {
        return $this->community;
    }

    public function setCommunity(string $community): static
    {
        $this->community = $community;

        return $this;
    }

    /**
     * @return Collection<int, DofusCharacter>
     */
    public function getDofusCharacters(): Collection
    {
        return $this->dofusCharacters;
    }

    public function addDofusCharacter(DofusCharacter $dofusCharacter): static
    {
        if (!$this->dofusCharacters->contains($dofusCharacter)) {
            $this->dofusCharacters->add($dofusCharacter);
            $dofusCharacter->setServer($this);
        }

        return $this;
    }

    public function removeDofusCharacter(DofusCharacter $dofusCharacter): static
    {
        if ($this->dofusCharacters->removeElement($dofusCharacter)) {
            // set the owning side to null (unless already changed)
            if ($dofusCharacter->getServer() === $this) {
                $dofusCharacter->setServer(null);
            }
        }

        return $this;
    }
}
