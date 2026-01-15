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
