<?php

namespace App\Entity;

use App\Repository\LotGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LotGroupRepository::class)]
class LotGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $lotSize = null;

    #[ORM\Column]
    private ?int $buyPricePerLot = null;

    #[ORM\Column]
    private ?int $sellPricePerLot = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'lotGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DofusCharacter $dofusCharacter = null;

    #[ORM\ManyToOne(inversedBy: 'lotGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

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

    public function setSellPricePerLot(int $sellPricePerLot): static
    {
        $this->sellPricePerLot = $sellPricePerLot;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
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
}
