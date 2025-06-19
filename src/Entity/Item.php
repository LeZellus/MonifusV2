<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $ankamaId = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $itemType = null;

    #[ORM\Column(nullable: true)]
    private ?int $level = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imgUrl = null;

    #[ORM\Column(nullable: true)]
    private ?float $xpPet = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnkamaId(): ?int
    {
        return $this->ankamaId;
    }

    public function setAnkamaId(?int $ankamaId): static
    {
        $this->ankamaId = $ankamaId;

        return $this;
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

    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    public function setItemType(?string $itemType): static
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getImgUrl(): ?string
    {
        return $this->imgUrl;
    }

    public function setImgUrl(?string $imgUrl): static
    {
        $this->imgUrl = $imgUrl;

        return $this;
    }

    public function getXpPet(): ?float
    {
        return $this->xpPet;
    }

    public function setXpPet(?float $xpPet): static
    {
        $this->xpPet = $xpPet;

        return $this;
    }
}
