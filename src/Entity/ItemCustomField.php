<?php

namespace App\Entity;

use App\Repository\ItemCustomFieldRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\FieldType;

#[ORM\Entity(repositoryClass: ItemCustomFieldRepository::class)]
class ItemCustomField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $fieldName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fieldValue = null;

    #[ORM\Column(enumType: FieldType::class)]
    private ?FieldType $fieldType = null;

    #[ORM\ManyToOne(inversedBy: 'itemCustomFields')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): static
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getFieldValue(): ?string
    {
        return $this->fieldValue;
    }

    public function setFieldValue(?string $fieldValue): static
    {
        $this->fieldValue = $fieldValue;

        return $this;
    }

    public function getFieldType(): ?FieldType
    {
        return $this->fieldType;
    }

    public function setFieldType(FieldType $fieldType): static
    {
        $this->fieldType = $fieldType;

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
