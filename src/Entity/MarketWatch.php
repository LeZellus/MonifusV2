<?php

namespace App\Entity;

use App\Repository\MarketWatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarketWatchRepository::class)]
class MarketWatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $lotSize = null;

    #[ORM\Column]
    private ?int $observedPrice = null;

    #[ORM\Column(length: 50)]
    private ?string $priceType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(inversedBy: 'marketWatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DofusCharacter $dofusCharacter = null;

    #[ORM\ManyToOne]
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

    public function getObservedPrice(): ?int
    {
        return $this->observedPrice;
    }

    public function setObservedPrice(int $observedPrice): static
    {
        $this->observedPrice = $observedPrice;

        return $this;
    }

    public function getPriceType(): ?string
    {
        return $this->priceType;
    }

    public function setPriceType(string $priceType): static
    {
        $this->priceType = $priceType;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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
