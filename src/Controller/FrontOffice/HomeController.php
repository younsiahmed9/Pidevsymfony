<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home_index', methods: ['GET'])]
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user instanceof User) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->redirectToRoute('admin_index');
            }

            return $this->redirectToRoute('front_dashboard_index');
        }

        return $this->render('frontoffice/home/index.html.twig');
    }

    #[Route('/dashboard/entry', name: 'app_dashboard_entry', methods: ['GET'])]
    public function dashboardEntry(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_index');
        }

        return $this->redirectToRoute('front_dashboard_index');
    }
}
