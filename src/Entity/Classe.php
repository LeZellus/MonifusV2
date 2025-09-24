<?php

namespace App\Entity;

use App\Repository\ClasseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClasseRepository::class)]
class Classe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imgUrl = null;

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

    public function getImgUrl(): ?string
    {
        return $this->imgUrl;
    }

    public function setImgUrl(?string $imgUrl): static
    {
        $this->imgUrl = $imgUrl;
        return $this;
    }

    /**
     * Retourne le chemin vers l'image de la classe
     */
    public function getImagePath(): string
    {
        // Utiliser d'abord imgUrl si disponible
        if ($this->imgUrl) {
            return $this->imgUrl;
        }

        // Sinon, générer le chemin à partir du nom en gérant les accents
        if ($this->name) {
            // Conversion des accents et normalisation pour les noms de fichiers
            $normalizedName = $this->normalizeClassName($this->name);
            $filename = strtolower($normalizedName) . '.png';
            return '/classes/' . $filename;
        }

        return '/classes/default.png';
    }

    /**
     * Normalise le nom de classe pour correspondre aux noms de fichiers
     */
    private function normalizeClassName(string $name): string
    {
        $replacements = [
            'Féca' => 'feca',
            'féca' => 'feca',
        ];

        return $replacements[$name] ?? $name;
    }
}