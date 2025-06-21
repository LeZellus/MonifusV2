<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\ItemType;

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

    #[ORM\Column(enumType: ItemType::class, nullable: true)]
    private ?ItemType $itemType = null;

    #[ORM\Column(nullable: true)]
    private ?int $level = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imgUrl = null;

    #[ORM\Column(nullable: true)]
    private ?float $xpPet = null;

    /**
     * @var Collection<int, LotGroup>
     */
    #[ORM\OneToMany(targetEntity: LotGroup::class, mappedBy: 'item', fetch: 'EXTRA_LAZY')]
    private Collection $lotGroups;

    /**
     * @var Collection<int, ItemCustomField>
     */
    #[ORM\OneToMany(targetEntity: ItemCustomField::class, mappedBy: 'item', fetch: 'EXTRA_LAZY')]
    private Collection $itemCustomFields;

    public function __construct()
    {
        $this->lotGroups = new ArrayCollection();
        $this->itemCustomFields = new ArrayCollection();
    }

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

    public function getItemType(): ?ItemType
    {
        return $this->itemType;
    }

    public function setItemType(?ItemType $itemType): static
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
            $lotGroup->setItem($this);
        }

        return $this;
    }

    public function removeLotGroup(LotGroup $lotGroup): static
    {
        if ($this->lotGroups->removeElement($lotGroup)) {
            // set the owning side to null (unless already changed)
            if ($lotGroup->getItem() === $this) {
                $lotGroup->setItem(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ItemCustomField>
     */
    public function getItemCustomFields(): Collection
    {
        return $this->itemCustomFields;
    }

    public function addItemCustomField(ItemCustomField $itemCustomField): static
    {
        if (!$this->itemCustomFields->contains($itemCustomField)) {
            $this->itemCustomFields->add($itemCustomField);
            $itemCustomField->setItem($this);
        }

        return $this;
    }

    public function removeItemCustomField(ItemCustomField $itemCustomField): static
    {
        if ($this->itemCustomFields->removeElement($itemCustomField)) {
            // set the owning side to null (unless already changed)
            if ($itemCustomField->getItem() === $this) {
                $itemCustomField->setItem(null);
            }
        }

        return $this;
    }
}
