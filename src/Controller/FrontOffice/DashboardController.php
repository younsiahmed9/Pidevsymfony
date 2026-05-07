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

        $conn = $entityManager->getConnection();
        $uid = $user->getId();

        $balanceTotal = (float) $conn->fetchOne(
            'SELECT COALESCE(SUM(solde_total), 0) FROM portefeuille WHERE user_id = :uid',
            ['uid' => $uid]
        );

        $nb_portefeuilles = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM portefeuille WHERE user_id = :uid',
            ['uid' => $uid]
        );

        $nb_cartes_actives = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM carte_virtuelle cv
             INNER JOIN portefeuille p ON cv.portefeuille_id = p.id
             WHERE p.user_id = :uid AND cv.is_active = 1',
            ['uid' => $uid]
        );

        $nb_virements_actifs = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM virement_programme WHERE user_id = :uid AND actif = 1',
            ['uid' => $uid]
        );

        $sqlDernieresTransactions = '
            SELECT t.id, t.`date`, t.description, t.type, t.statut, t.montant, t.devise
            FROM transaction t
            WHERE EXISTS (
              SELECT 1 FROM carte_virtuelle cv
              INNER JOIN portefeuille p ON cv.portefeuille_id = p.id
              WHERE p.user_id = :uid AND (cv.id = t.carte_source_id OR cv.id = t.carte_dest_id)
            )
            ORDER BY t.`date` DESC
            LIMIT 10';

        $dernieres_transactions = $conn->fetchAllAssociative($sqlDernieresTransactions, ['uid' => $uid]);

        return $this->render('frontoffice/dashboard/index.html.twig', [
            'balanceTotal' => $balanceTotal,
            'nb_portefeuilles' => $nb_portefeuilles,
            'nb_cartes_actives' => $nb_cartes_actives,
            'nb_virements_actifs' => $nb_virements_actifs,
            'dernieres_transactions' => $dernieres_transactions,
        ]);
    }
}
