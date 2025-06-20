<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DiscordController extends AbstractController
{
    #[Route('/connect/discord', name: 'connect_discord_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Rediriger vers Discord pour l'authentification
        return $clientRegistry
            ->getClient('discord')
            ->redirect([
                'identify', // Permet de récupérer l'ID et username Discord
                'email'     // Permet de récupérer l'email Discord
            ]);
    }

    #[Route('/connect/discord/check', name: 'connect_discord_check')]
    public function connectCheckAction(Request $request, ClientRegistry $clientRegistry)
    {
        // Cette méthode peut être vide car l'authenticator Discord gère tout
        // Le contrôleur ne sera jamais exécuté car l'authenticator intercepte la requête
        
        // En cas d'erreur dans l'authenticator, on peut arriver ici
        throw new \Exception('Ne devrait pas arriver. Vérifiez votre configuration Discord.');
    }
}