<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DiscordAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private RouterInterface $router,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Cette authenticator ne fonctionne que sur la route de callback Discord
        return $request->attributes->get('_route') === 'connect_discord_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $client) {
                /** @var \Wohali\OAuth2\Client\Provider\DiscordResourceOwner $discordUser */
                $discordUser = $client->fetchUserFromToken($accessToken);

                // Récupérer les infos Discord
                $discordId = $discordUser->getId();
                $email = $discordUser->getEmail();
                $username = $discordUser->getUsername();
                $avatar = $discordUser->getAvatarHash();

                // Chercher un utilisateur existant par Discord ID d'abord
                $existingUser = $this->userRepository->findOneBy(['discordId' => $discordId]);
                
                if ($existingUser) {
                    // Mettre à jour les infos Discord si nécessaire
                    $existingUser->setDiscordUsername($username);
                    $existingUser->setDiscordAvatar($avatar);
                    $this->entityManager->flush();
                    return $existingUser;
                }

                // Chercher par email si pas trouvé par Discord ID
                if ($email) {
                    $existingUser = $this->userRepository->findOneBy(['email' => $email]);
                    if ($existingUser) {
                        // Lier le compte Discord à un compte existant
                        $existingUser->setDiscordId($discordId);
                        $existingUser->setDiscordUsername($username);
                        // $existingUser->setDiscordAvatar($avatar);
                        $this->entityManager->flush();
                        return $existingUser;
                    }
                }

                // Créer un nouvel utilisateur
                $user = new User();
                $user->setEmail($email ?: 'discord_' . $discordId . '@placeholder.com');
                $user->setDiscordId($discordId);
                $user->setDiscordUsername($username);
                $user->setDiscordAvatar($avatar);
                $user->setPseudonymeWebsite($username);
                $user->setIsVerified(true); // Discord users are verified
                $user->setIsTutorial(true); // Nouveaux utilisateurs voient le tutoriel
                
                // Générer un mot de passe aléatoire (non utilisé mais requis)
                $randomPassword = bin2hex(random_bytes(20));
                $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Rediriger vers le dashboard après connexion réussie
        return new RedirectResponse($this->router->generate('app_trading_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new RedirectResponse($this->router->generate('app_login', [
            'error' => 'Erreur lors de la connexion Discord: ' . $message
        ]));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->router->generate('connect_discord_start'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}