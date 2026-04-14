<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'front_dashboard_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_index');
        }

        $balanceTotal = (float) $entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(solde_total), 0) FROM portefeuille WHERE user_id = :uid',
            ['uid' => $user->getId()]
        );

        return $this->render('frontoffice/dashboard/index.html.twig', [
            'balanceTotal' => $balanceTotal,
        ]);
    }
}
