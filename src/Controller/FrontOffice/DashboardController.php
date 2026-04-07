<?php

namespace App\Controller\FrontOffice;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/user/dashboard', name: 'front_dashboard_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        if (strtolower(trim((string) $user->getUserIdentifier())) === 'admin@fintrack.com') {
            return $this->redirectToRoute('admin_index');
        }

        return $this->render('frontoffice/dashboard/index.html.twig');
    }
}
