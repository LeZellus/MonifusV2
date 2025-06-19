<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ClasseRepository;
use App\Repository\ServerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        UserRepository $userRepository,
        ClasseRepository $classeRepository,
        ServerRepository $serverRepository
    ): Response {
        return $this->render('home/index.html.twig', [
            'users_count' => $userRepository->count([]),
            'classes_count' => $classeRepository->count([]),
            'servers_count' => $serverRepository->count([]),
        ]);
    }
}