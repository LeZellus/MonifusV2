<?php

namespace App\Entity;

use App\Repository\LotGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\LotStatus;
use App\Enum\SaleUnit;

#[ORM\Entity(repositoryClass: LotGroupRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LotGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $lotSize = null;

    #[ORM\Column(type: 'bigint')]
    private ?int $buyPricePerLot = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $sellPricePerLot = null;

    #[ORM\Column(enumType: LotStatus::class)]
    private ?LotStatus $status = null;

    #[ORM\ManyToOne(inversedBy: 'lotGroups')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DofusCharacter $dofusCharacter = null;

    #[ORM\ManyToOne(inversedBy: 'lotGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    #[ORM\Column(enumType: SaleUnit::class)]
    private ?SaleUnit $buyUnit = null;

    #[ORM\Column(enumType: SaleUnit::class)]
    private ?SaleUnit $saleUnit = null;

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
     * @var Collection<int, LotUnit>
     */
    #[ORM\OneToMany(targetEntity: LotUnit::class, mappedBy: 'lotGroup', cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    private Collection $lotUnits;

    public function __construct()
    {
        $this->lotUnits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLotSize(): ?int
    {
        return $this->lotSize;
    }

    public function setLotSize(int $lotSize): static
    {
        $this->lotSize = $lotSize;

        return $this;
    }

    public function getBuyPricePerLot(): ?int
    {
        return $this->buyPricePerLot;
    }

    public function setBuyPricePerLot(int $buyPricePerLot): static
    {
        $this->buyPricePerLot = $buyPricePerLot;

        return $this;
    }

    public function getSellPricePerLot(): ?int
    {
        return $this->sellPricePerLot;
    }

    public function setSellPricePerLot(?int $sellPricePerLot): static
    {
        $this->sellPricePerLot = $sellPricePerLot;

        return $this;
    }

    public function getStatus(): ?LotStatus
    {
        return $this->status;
    }

    public function setStatus(LotStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDofusCharacter(): ?DofusCharacter
    {
        return $this->dofusCharacter;
    }

    public function setDofusCharacter(?DofusCharacter $dofusCharacter): static
    {
        $this->dofusCharacter = $dofusCharacter;

        return $this;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        $this->item = $item;

        return $this;
    }

    public function getBuyUnit(): ?SaleUnit
    {
        return $this->buyUnit;
    }

    public function setBuyUnit(SaleUnit $buyUnit): static
    {
        $this->buyUnit = $buyUnit;
        return $this;
    }

    public function getSaleUnit(): ?SaleUnit
    {
        return $this->saleUnit;
    }

    public function setSaleUnit(SaleUnit $saleUnit): static
    {
        $this->saleUnit = $saleUnit;
        return $this;
    }

    /**
     * Calcule la quantité totale d'items
     * = nombre de lots achetés × taille du lot d'achat
     */
    public function getTotalItems(): int
    {
        return $this->lotSize * ($this->buyUnit?->value ?? 1);
    }

    /**
     * Calcule le nombre de lots de revente
     * = quantité totale d'items / taille du lot de revente
     */
    public function getSellLotCount(): int
    {
        $saleUnitValue = $this->saleUnit?->value ?? 1;
        return $saleUnitValue > 0 ? (int) ($this->getTotalItems() / $saleUnitValue) : 0;
    }

    /**
     * Calcule l'investissement total
     * = nombre de lots achetés × prix d'achat par lot
     */
    public function getTotalInvestment(): int
    {
        return $this->lotSize * ($this->buyPricePerLot ?? 0);
    }

    /**
     * Calcule le revenu total potentiel
     * = nombre de lots de revente × prix de vente par lot
     */
    public function getTotalRevenue(): int
    {
        return $this->getSellLotCount() * ($this->sellPricePerLot ?? 0);
    }

    /**
     * Calcule le profit total potentiel
     * = revenu total - investissement total
     */
    public function getTotalProfit(): int
    {
        return $this->getTotalRevenue() - $this->getTotalInvestment();
    }

    /**
     * Calcule le coût par item
     * = prix d'achat par lot / taille du lot d'achat
     */
    public function getCostPerItem(): float
    {
        $buyUnitValue = $this->buyUnit?->value ?? 1;
        return $buyUnitValue > 0 ? ($this->buyPricePerLot ?? 0) / $buyUnitValue : 0;
    }

    /**
     * Calcule le prix de vente par item
     * = prix de vente par lot / taille du lot de vente
     */
    public function getSellPricePerItem(): float
    {
        $saleUnitValue = $this->saleUnit?->value ?? 1;
        return $saleUnitValue > 0 ? ($this->sellPricePerLot ?? 0) / $saleUnitValue : 0;
    }

    /**
     * @return Collection<int, LotUnit>
     */
    public function getLotUnits(): Collection
    {
        return $this->lotUnits;
    }

    public function addLotUnit(LotUnit $lotUnit): static
    {
        if (!$this->lotUnits->contains($lotUnit)) {
            $this->lotUnits->add($lotUnit);
            $lotUnit->setLotGroup($this);
        }

        return $this;
    }

    public function removeLotUnit(LotUnit $lotUnit): static
    {
        if ($this->lotUnits->removeElement($lotUnit)) {
            // set the owning side to null (unless already changed)
            if ($lotUnit->getLotGroup() === $this) {
                $lotUnit->setLotGroup(null);
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
