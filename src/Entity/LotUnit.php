<?php

namespace App\Entity;

use App\Repository\LotUnitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LotUnitRepository::class)]
class LotUnit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $soldAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $actualSellPrice = null;

    #[ORM\Column]
    private ?int $quantitySold = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(inversedBy: 'lotUnits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LotGroup $lotGroup = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'lotUnit')]
    private Collection $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSoldAt(): ?\DateTime
    {
        return $this->soldAt;
    }

    public function setSoldAt(?\DateTime $soldAt): static
    {
        $this->soldAt = $soldAt;
        return $this;
    }

    public function getActualSellPrice(): ?int
    {
        return $this->actualSellPrice;
    }

    public function setActualSellPrice(?int $actualSellPrice): static
    {
        $this->actualSellPrice = $actualSellPrice;
        return $this;
    }

    public function getQuantitySold(): ?int
    {
        return $this->quantitySold;
    }

    public function setQuantitySold(?int $quantitySold): static
    {
        $this->quantitySold = $quantitySold;
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

    public function getLotGroup(): ?LotGroup
    {
        return $this->lotGroup;
    }

    public function setLotGroup(?LotGroup $lotGroup): static
    {
        $this->lotGroup = $lotGroup;
        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setLotUnit($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getLotUnit() === $this) {
                $comment->setLotUnit(null);
            }
        }
        return $this;
    }
}