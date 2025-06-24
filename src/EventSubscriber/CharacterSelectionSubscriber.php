<?php
// Version alternative si Security ne fonctionne pas
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CharacterSelectionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }
        
        $user = $token->getUser();
        if (!$user) {
            return;
        }

        // Sauvegarder le personnage sélectionné dans un cookie
        $characterId = $session->get('selected_character_id');
        if ($characterId) {
            $cookie = Cookie::create("last_character_user_{$user->getId()}")
                ->withValue((string)$characterId)
                ->withExpires(time() + (30 * 24 * 60 * 60)) // 30 jours
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite('Lax');
                
            $response->headers->setCookie($cookie);
        }
    }
}