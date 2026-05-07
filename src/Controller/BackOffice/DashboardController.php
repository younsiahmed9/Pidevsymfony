<?php

namespace App\Controller\BackOffice;

use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_index', methods: ['GET'])]
    public function adminIndex(): Response
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = trim((string) $request->query->get('q', ''));
        $sort = strtolower(trim((string) $request->query->get('sort', 'created_at')));
        $direction = strtolower(trim((string) $request->query->get('direction', 'desc')));

        $sortMap = [
            'nsc' => 'u.id',
            'nom' => 'u.full_name',
            'email' => 'u.email',
            'role' => 'u.role',
            'solde' => 'solde',
            'created_at' => 'u.created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'u.created_at';
        $sortDirection = $direction === 'asc' ? 'ASC' : 'DESC';

        $kpiSql = 'SELECT
            (SELECT COUNT(*) FROM users) AS nb_utilisateurs,
            (SELECT COUNT(*) FROM portefeuille) AS nb_portefeuilles,
            (SELECT COUNT(*) FROM carte_virtuelle) AS nb_cartes,
            (SELECT COUNT(*) FROM transaction) AS nb_transactions';

        $kpis = $entityManager->getConnection()->fetchAssociative($kpiSql) ?: [
            'nb_utilisateurs' => 0,
            'nb_portefeuilles' => 0,
            'nb_cartes' => 0,
            'nb_transactions' => 0,
        ];

        $userSql = 'SELECT
                u.id,
                TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS nom,
                TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS prenom,
                u.email,
                LOWER(u.role) AS role,
                u.is_active,
                (
                    SELECT COALESCE(SUM(p.solde_total), 0)
                    FROM portefeuille p
                    WHERE p.user_id = u.id
                ) AS solde,
                u.created_at,
                u.updated_at
            FROM users u
            WHERE 1=1';

        $params = [];
        if ($search !== '') {
            $userSql .= ' AND (
                u.full_name LIKE :q
                OR u.email LIKE :q
                OR u.role LIKE :q
                OR CAST(u.id AS CHAR) LIKE :q
            )';
            $params['q'] = '%' . $search . '%';
        }

        $userSql .= sprintf(' ORDER BY %s %s', $sortColumn, $sortDirection);

        $users = $entityManager->getConnection()->fetchAllAssociative($userSql, $params);
        $portefeuilleFilters = $this->buildPortefeuilleFilters($request);
        $portefeuilles = $this->fetchFilteredPortefeuilles($entityManager, $portefeuilleFilters);
        $portefeuilleStats = $this->fetchPortefeuilleStats($entityManager, $portefeuilleFilters);
        $transferStats = $this->fetchTransferStats($entityManager);

        $administrators = 0;
        $activeClients = 0;

        foreach ($users as $user) {
            $role = strtolower((string) ($user['role'] ?? ''));
            if ($role === 'admin') {
                ++$administrators;
            } elseif ((int) ($user['is_active'] ?? 0) === 1) {
                ++$activeClients;
            }
        }

        $totalUsers = (int) ($kpis['nb_utilisateurs'] ?? 0);
        $activeUsers = 0;
        foreach ($users as $user) {
            if ((int) ($user['is_active'] ?? 0) === 1) {
                ++$activeUsers;
            }
        }

        $stats = [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'activeClients' => $activeClients,
            'administrators' => $administrators,
        ];

        return $this->render('backoffice/dashboard/index.html.twig', [
            'kpis' => $kpis,
            'stats' => $stats,
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($sortDirection),
            'portefeuilleFilters' => $portefeuilleFilters,
            'portefeuilles' => $portefeuilles,
            'portefeuilleStats' => $portefeuilleStats,
            'transferStats' => $transferStats,
        ]);
    }

    #[Route('/dashboard/portefeuilles/export/pdf', name: 'admin_portefeuille_export_pdf', methods: ['GET'])]
    public function exportPortefeuillesPdf(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filters = $this->buildPortefeuilleFilters($request);
        $portefeuilles = $this->fetchFilteredPortefeuilles($entityManager, $filters);

        $html = $this->renderView('backoffice/portefeuille/export_pdf.html.twig', [
            'portefeuilles' => $portefeuilles,
            'filters' => $filters,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('fintrack-admin-portefeuilles-%s.pdf', (new \DateTimeImmutable())->format('Ymd-His'));

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    private function buildPortefeuilleFilters(Request $request): array
    {
        $sort = strtolower(trim((string) $request->query->get('pf_sort', 'created_at')));
        $direction = strtolower(trim((string) $request->query->get('pf_direction', 'desc')));

        return [
            'q' => trim((string) $request->query->get('pf_q', '')),
            'sort' => $sort,
            'direction' => $direction === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function fetchFilteredPortefeuilles(EntityManagerInterface $entityManager, array $filters): array
    {
        $sortMap = [
            'id' => 'p.id',
            'nom' => 'p.nom',
            'solde_total' => 'p.solde_total',
            'devise' => 'p.devise_principale',
            'owner' => 'u.email',
            'created_at' => 'p.created_at',
        ];

        $sortColumn = $sortMap[$filters['sort']] ?? 'p.created_at';
        $sortDirection = $filters['direction'] === 'asc' ? 'ASC' : 'DESC';

        $sql = 'SELECT
                p.id,
                p.nom,
                p.solde_total,
                p.devise_principale,
                p.created_at,
                p.updated_at,
                u.id AS owner_id,
                TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS owner_nom,
                TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS owner_prenom,
                u.email AS owner_email
            FROM portefeuille p
            INNER JOIN users u ON u.id = p.user_id
            WHERE 1=1';

        $params = [];
        if ($filters['q'] !== '') {
            $sql .= ' AND (
                p.nom LIKE :q
                OR p.devise_principale LIKE :q
                OR u.email LIKE :q
                OR u.full_name LIKE :q
                OR CAST(p.id AS CHAR) LIKE :q
            )';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= sprintf(' ORDER BY %s %s', $sortColumn, $sortDirection);

        return $entityManager->getConnection()->fetchAllAssociative($sql, $params);
    }

    private function fetchPortefeuilleStats(EntityManagerInterface $entityManager, array $filters): array
    {
        $params = [];
        $whereSql = '';

        if ($filters['q'] !== '') {
            $whereSql = ' AND (
                p.nom LIKE :q
                OR p.devise_principale LIKE :q
                OR u.email LIKE :q
                OR u.full_name LIKE :q
                OR CAST(p.id AS CHAR) LIKE :q
            )';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $portfolioSummarySql = 'SELECT
                COUNT(*) AS total_portefeuilles,
                COALESCE(SUM(p.solde_total), 0) AS total_solde,
                COUNT(DISTINCT p.user_id) AS total_owners,
                COUNT(DISTINCT p.devise_principale) AS total_devises
            FROM portefeuille p
            INNER JOIN users u ON u.id = p.user_id
            WHERE 1=1' . $whereSql;

        $portfolioSummary = $entityManager->getConnection()->fetchAssociative($portfolioSummarySql, $params) ?: [];

        $cardSummarySql = 'SELECT
                COUNT(c.id) AS total_cards,
                COALESCE(SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_cards
            FROM portefeuille p
            INNER JOIN users u ON u.id = p.user_id
            LEFT JOIN carte_virtuelle c ON c.portefeuille_id = p.id
            WHERE 1=1' . $whereSql;

        $cardSummary = $entityManager->getConnection()->fetchAssociative($cardSummarySql, $params) ?: [];

        $cardTypesSql = 'SELECT
                COALESCE(c.type, "Inconnu") AS type,
                COUNT(c.id) AS total
            FROM portefeuille p
            INNER JOIN users u ON u.id = p.user_id
            INNER JOIN carte_virtuelle c ON c.portefeuille_id = p.id
            WHERE 1=1' . $whereSql . '
            GROUP BY c.type
            ORDER BY total DESC';

        $cardTypes = $entityManager->getConnection()->fetchAllAssociative($cardTypesSql, $params);

        return [
            'totalPortefeuilles' => (int) ($portfolioSummary['total_portefeuilles'] ?? 0),
            'totalSolde' => (float) ($portfolioSummary['total_solde'] ?? 0),
            'totalOwners' => (int) ($portfolioSummary['total_owners'] ?? 0),
            'totalDevises' => (int) ($portfolioSummary['total_devises'] ?? 0),
            'totalCards' => (int) ($cardSummary['total_cards'] ?? 0),
            'activeCards' => (int) ($cardSummary['active_cards'] ?? 0),
            'cardTypes' => $cardTypes,
        ];
    }

    private function fetchTransferStats(EntityManagerInterface $entityManager): array
    {
        $summary = $entityManager->getConnection()->fetchAssociative(
            'SELECT
                COALESCE(SUM(CASE WHEN t.type = "TRANSFERT" THEN 1 ELSE 0 END), 0) AS normal_transfers,
                COALESCE(SUM(CASE WHEN t.type IN ("VIREMENT_PROGRAMME", "TRANSFERT_PROGRAMME") THEN 1 ELSE 0 END), 0) AS programmed_transfers,
                COALESCE(SUM(CASE WHEN t.type IN ("TRANSFERT", "VIREMENT_PROGRAMME", "TRANSFERT_PROGRAMME") THEN t.montant ELSE 0 END), 0) AS transferred_amount
             FROM transaction t
               WHERE t.statut IN ("SUCCESS", "COMPLETED")'
        ) ?: [];

        $fees = $entityManager->getConnection()->fetchAssociative(
            'SELECT
                COALESCE(SUM(fee_amount), 0) AS total_fees,
                COALESCE(SUM(CASE WHEN applied_fee_rate = 0.01 THEN 1 ELSE 0 END), 0) AS discounted_transfers
             FROM transfer_fee_event'
        ) ?: [];

        return [
            'normalTransfers' => (int) ($summary['normal_transfers'] ?? 0),
            'programmedTransfers' => (int) ($summary['programmed_transfers'] ?? 0),
            'transferredAmount' => (float) ($summary['transferred_amount'] ?? 0),
            'totalFees' => (float) ($fees['total_fees'] ?? 0),
            'discountedTransfers' => (int) ($fees['discounted_transfers'] ?? 0),
        ];
    }
}
