<?php

namespace App\Entity;

use App\Repository\MarketWatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarketWatchRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MarketWatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    #[ORM\ManyToOne(inversedBy: 'marketWatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DofusCharacter $dofusCharacter = null;

    // Prix par unité (peut être null si pas observé)
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $pricePerUnit = null;

    // Prix par lot de 10 (peut être null si pas observé)
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $pricePer10 = null;

    // Prix par lot de 100 (peut être null si pas observé)
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $pricePer100 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $observedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $pricePer1000 = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        if (!$this->observedAt) {
            $this->observedAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDofusCharacter(): ?DofusCharacter
    {
        return $this->dofusCharacter;
    }

    public function setDofusCharacter(?DofusCharacter $dofusCharacter): static
    {
        $this->dofusCharacter = $dofusCharacter;
        return $this;
    }

    public function getPricePerUnit(): ?int
    {
        return $this->pricePerUnit;
    }

    public function setPricePerUnit(?int $pricePerUnit): static
    {
        $this->pricePerUnit = $pricePerUnit;
        return $this;
    }

    public function getPricePer10(): ?int
    {
        return $this->pricePer10;
    }

    public function setPricePer10(?int $pricePer10): static
    {
        $this->pricePer10 = $pricePer10;
        return $this;
    }

    public function getPricePer100(): ?int
    {
        return $this->pricePer100;
    }

    public function setPricePer100(?int $pricePer100): static
    {
        $this->pricePer100 = $pricePer100;
        return $this;
    }

    public function getPricePer1000(): ?int
    {
        return $this->pricePer1000;
    }

    public function setPricePer1000(?int $pricePer1000): static
    {
        $this->pricePer1000 = $pricePer1000;

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

    public function getObservedAt(): ?\DateTimeImmutable
    {
        return $this->observedAt;
    }

    public function setObservedAt(?\DateTimeImmutable $observedAt): static
    {
        $this->observedAt = $observedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Retourne true si au moins un prix est renseigné
     */
    public function hasAnyPrice(): bool
    {
        return $this->pricePerUnit !== null || 
               $this->pricePer10 !== null || 
               $this->pricePer100 !== null ||
               $this->pricePer1000 !== null;
    }

    /**
     * Retourne le nombre d'unités pour lesquelles on a un prix
     */
    public function getPriceCount(): int
    {
        $count = 0;
        if ($this->pricePerUnit !== null) $count++;
        if ($this->pricePer10 !== null) $count++;
        if ($this->pricePer100 !== null) $count++;
        if ($this->pricePer1000 !== null) $count++; // MODIFIÉ : Inclure pricePer1000
        return $count;
    }

    /**
     * Retourne un tableau des prix disponibles avec leurs unités
     */
    public function getAvailablePrices(): array
    {
        $prices = [];
        
        if ($this->pricePerUnit !== null) {
            $prices['x1'] = $this->pricePerUnit;
        }
        
        if ($this->pricePer10 !== null) {
            $prices['x10'] = $this->pricePer10;
        }
        
        if ($this->pricePer100 !== null) {
            $prices['x100'] = $this->pricePer100;
        }

        if ($this->pricePer1000 !== null) {
            $prices['x1000'] = $this->pricePer1000;
        }
        
        return $prices;
    }
}