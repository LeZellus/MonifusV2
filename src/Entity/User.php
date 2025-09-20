<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pseudonymeWebsite = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pseudonymeDofus = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isVerified = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $coverPicture = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discordUsername = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordAvatar = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isTutorial = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $youtubeUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $twitterUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ankamaUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $twitchUrl = null;

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
     * @var Collection<int, TradingProfile>
     */
    #[ORM\OneToMany(targetEntity: TradingProfile::class, mappedBy: 'user', fetch: 'EXTRA_LAZY')]
    private Collection $tradingProfiles;

    public function __construct()
    {
        $this->tradingProfiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getPseudonymeWebsite(): ?string
    {
        return $this->pseudonymeWebsite;
    }

    public function setPseudonymeWebsite(?string $pseudonymeWebsite): static
    {
        $this->pseudonymeWebsite = $pseudonymeWebsite;

        return $this;
    }

    public function getPseudonymeDofus(): ?string
    {
        return $this->pseudonymeDofus;
    }

    public function setPseudonymeDofus(?string $pseudonymeDofus): static
    {
        $this->pseudonymeDofus = $pseudonymeDofus;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(?bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    public function getCoverPicture(): ?string
    {
        return $this->coverPicture;
    }

    public function setCoverPicture(?string $coverPicture): static
    {
        $this->coverPicture = $coverPicture;

        return $this;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(?string $discordId): static
    {
        $this->discordId = $discordId;

        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(?string $discordUsername): static
    {
        $this->discordUsername = $discordUsername;

        return $this;
    }

    public function getDiscordAvatar(): ?string
    {
        return $this->discordAvatar;
    }

    public function setDiscordAvatar(?string $discordAvatar): static
    {
        $this->discordAvatar = $discordAvatar;
        return $this;
    }

    public function isTutorial(): ?bool
    {
        return $this->isTutorial;
    }

    public function setIsTutorial(?bool $isTutorial): static
    {
        $this->isTutorial = $isTutorial;

        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        return $this;
    }

    public function getTwitterUrl(): ?string
    {
        return $this->twitterUrl;
    }

    public function setTwitterUrl(?string $twitterUrl): static
    {
        $this->twitterUrl = $twitterUrl;

        return $this;
    }

    public function getAnkamaUrl(): ?string
    {
        return $this->ankamaUrl;
    }

    public function setAnkamaUrl(?string $ankamaUrl): static
    {
        $this->ankamaUrl = $ankamaUrl;

        return $this;
    }

    public function getTwitchUrl(): ?string
    {
        return $this->twitchUrl;
    }

    public function setTwitchUrl(?string $twitchUrl): static
    {
        $this->twitchUrl = $twitchUrl;

        return $this;
    }

    /**
     * @return Collection<int, TradingProfile>
     */
    public function getTradingProfiles(): Collection
    {
        return $this->tradingProfiles;
    }

    public function addTradingProfile(TradingProfile $tradingProfile): static
    {
        if (!$this->tradingProfiles->contains($tradingProfile)) {
            $this->tradingProfiles->add($tradingProfile);
            $tradingProfile->setUser($this);
        }

        return $this;
    }

    public function removeTradingProfile(TradingProfile $tradingProfile): static
    {
        if ($this->tradingProfiles->removeElement($tradingProfile)) {
            // set the owning side to null (unless already changed)
            if ($tradingProfile->getUser() === $this) {
                $tradingProfile->setUser(null);
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

    /**
     * Retourne l'URL complète de l'avatar Discord
     */
    public function getDiscordAvatarUrl(?string $size = '128'): ?string
    {
        if (!$this->discordId || !$this->discordAvatar) {
            return null;
        }
        
        return sprintf(
            'https://cdn.discordapp.com/avatars/%s/%s.png?size=%s',
            $this->discordId,
            $this->discordAvatar,
            $size
        );
    }

    /**
     * Retourne l'avatar par défaut Discord si pas d'avatar custom
     */
    public function getDiscordDefaultAvatarUrl(): string
    {
        if (!$this->discordId) {
            return '';
        }
        
        // Discord calcule l'avatar par défaut avec (user_id >> 22) % 6
        $defaultIndex = (int)($this->discordId >> 22) % 6;
        return "https://cdn.discordapp.com/embed/avatars/{$defaultIndex}.png";
    }

    /**
     * Retourne la meilleure URL d'avatar disponible
     */
    public function getBestAvatarUrl(string $size = '128'): ?string
    {
        // Priorité : Discord > avatar uploadé > défaut Discord
        if ($url = $this->getDiscordAvatarUrl($size)) {
            return $url;
        }
        
        if ($this->profilePicture) {
            return $this->profilePicture;
        }
        
        if ($this->discordId) {
            return $this->getDiscordDefaultAvatarUrl();
        }
        
        return null;
    }

    /**
     * Méthode helper pour savoir si l'utilisateur s'est connecté via Discord
     */
    public function isDiscordUser(): bool
    {
        return $this->discordId !== null;
    }
}
