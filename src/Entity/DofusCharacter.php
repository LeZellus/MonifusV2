<?php

namespace App\Entity;

use App\Repository\DofusCharacterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DofusCharacterRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DofusCharacter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

   #[ORM\ManyToOne(targetEntity: TradingProfile::class, inversedBy: 'dofusCharacters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TradingProfile $tradingProfile = null;

    #[ORM\ManyToOne(inversedBy: 'dofusCharacters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Server $server = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Classe $classe = null;

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
     * @var Collection<int, LotGroup>
     */
    #[ORM\OneToMany(targetEntity: LotGroup::class, mappedBy: 'dofusCharacter')]
    private Collection $lotGroups;

    /**
     * @var Collection<int, MarketWatch>
     */
    #[ORM\OneToMany(targetEntity: MarketWatch::class, mappedBy: 'dofusCharacter')]
    private Collection $marketWatches;

    public function __construct()
    {
        $this->lotGroups = new ArrayCollection();
        $this->marketWatches = new ArrayCollection();
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

    public function getTradingProfile(): ?TradingProfile
    {
        return $this->tradingProfile;
    }

    public function setTradingProfile(?TradingProfile $tradingProfile): static
    {
        $this->tradingProfile = $tradingProfile;

        return $this;
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function setClasse(?Classe $classe): static
    {
        $this->classe = $classe;

        return $this;
    }

    /**
     * @return Collection<int, LotGroup>
     */
    public function getLotGroups(): Collection
    {
        return $this->lotGroups;
    }

    public function addLotGroup(LotGroup $lotGroup): static
    {
        if (!$this->lotGroups->contains($lotGroup)) {
            $this->lotGroups->add($lotGroup);
            $lotGroup->setDofusCharacter($this);
        }

        return $this;
    }

    public function removeLotGroup(LotGroup $lotGroup): static
    {
        if ($this->lotGroups->removeElement($lotGroup)) {
            // set the owning side to null (unless already changed)
            if ($lotGroup->getDofusCharacter() === $this) {
                $lotGroup->setDofusCharacter(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MarketWatch>
     */
    public function getMarketWatches(): Collection
    {
        return $this->marketWatches;
    }

    public function addMarketWatch(MarketWatch $marketWatch): static
    {
        if (!$this->marketWatches->contains($marketWatch)) {
            $this->marketWatches->add($marketWatch);
            $marketWatch->setDofusCharacter($this);
        }

        return $this;
    }

    public function removeMarketWatch(MarketWatch $marketWatch): static
    {
        if ($this->marketWatches->removeElement($marketWatch)) {
            // set the owning side to null (unless already changed)
            if ($marketWatch->getDofusCharacter() === $this) {
                $marketWatch->setDofusCharacter(null);
            }
        }

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
}
