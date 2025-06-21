<?php

namespace App\Entity;

use App\Repository\TradingProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: TradingProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TradingProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'tradingProfiles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @var Collection<int, DofusCharacter>
     */
    #[ORM\OneToMany(targetEntity: DofusCharacter::class, mappedBy: 'tradingProfile', cascade: ['remove'], fetch: 'EXTRA_LAZY')]
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
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

    // Getters
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
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
            $dofusCharacter->setTradingProfile($this);
        }

        return $this;
    }

    public function removeDofusCharacter(DofusCharacter $dofusCharacter): static
    {
        if ($this->dofusCharacters->removeElement($dofusCharacter)) {
            // set the owning side to null (unless already changed)
            if ($dofusCharacter->getTradingProfile() === $this) {
                $dofusCharacter->setTradingProfile(null);
            }
        }

        return $this;
    }
}
