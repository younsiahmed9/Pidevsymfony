<?php

namespace App\Controller\BackOffice;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/wallets', name: 'admin_wallet_')]
final class WalletAdminController extends AbstractController
{
    #[Route('', name: 'users', methods: ['GET'])]
    public function users(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = trim((string) $request->query->get('q', ''));
        $sort = strtolower((string) $request->query->get('sort', 'created_at'));
        $direction = strtolower((string) $request->query->get('direction', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'u.id',
            'name' => 'u.full_name',
            'email' => 'u.email',
            'status' => 'u.is_active',
            'wallets' => 'wallets_count',
            'balance' => 'wallets_balance',
            'cards' => 'cards_count',
            'created_at' => 'u.created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'u.created_at';

        $sql = 'SELECT
                u.id,
                u.full_name,
                u.email,
                u.is_active,
                u.created_at,
                COALESCE(COUNT(DISTINCT p.id), 0) AS wallets_count,
                COALESCE(SUM(p.solde_total), 0) AS wallets_balance,
                COALESCE(COUNT(c.id), 0) AS cards_count,
                COALESCE(SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_cards_count
            FROM users u
            LEFT JOIN portefeuille p ON p.user_id = u.id
            LEFT JOIN carte_virtuelle c ON c.portefeuille_id = p.id
            WHERE 1=1';

        $params = [];
        if ($search !== '') {
            $sql .= ' AND (
                u.full_name LIKE :q
                OR u.email LIKE :q
                OR CAST(u.id AS CHAR) LIKE :q
            )';
            $params['q'] = '%' . $search . '%';
        }

        $sql .= ' GROUP BY u.id, u.full_name, u.email, u.is_active, u.created_at';
        $sql .= sprintf(' ORDER BY %s %s', $sortColumn, $direction);

        $users = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        $kpis = $entityManager->getConnection()->fetchAssociative(
            'SELECT
                COUNT(DISTINCT u.id) AS total_users,
                COUNT(DISTINCT p.id) AS total_wallets,
                COALESCE(SUM(p.solde_total), 0) AS total_balance,
                COUNT(c.id) AS total_cards,
                COALESCE(SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_cards
             FROM users u
             LEFT JOIN portefeuille p ON p.user_id = u.id
             LEFT JOIN carte_virtuelle c ON c.portefeuille_id = p.id'
        ) ?: [];

        return $this->render('backoffice/wallet/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'kpis' => [
                'totalUsers' => (int) ($kpis['total_users'] ?? 0),
                'totalWallets' => (int) ($kpis['total_wallets'] ?? 0),
                'totalBalance' => (float) ($kpis['total_balance'] ?? 0),
                'totalCards' => (int) ($kpis['total_cards'] ?? 0),
                'activeCards' => (int) ($kpis['active_cards'] ?? 0),
            ],
        ]);
    }

    #[Route('/user/{id}', name: 'user_wallets', methods: ['GET'])]
    public function userWallets(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $entityManager->getConnection()->fetchAssociative(
            'SELECT id, full_name, email, is_active, created_at FROM users WHERE id = :id',
            ['id' => $id]
        );

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = strtolower((string) $request->query->get('sort', 'created_at'));
        $direction = strtolower((string) $request->query->get('direction', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'p.id',
            'name' => 'p.nom',
            'currency' => 'p.devise_principale',
            'balance' => 'p.solde_total',
            'cards' => 'cards_count',
            'active_cards' => 'active_cards_count',
            'created_at' => 'p.created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'p.created_at';

        $sql = 'SELECT
                p.id,
                p.nom,
                p.solde_total,
                p.devise_principale,
                p.created_at,
                p.updated_at,
                COALESCE(COUNT(c.id), 0) AS cards_count,
                COALESCE(SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_cards_count
            FROM portefeuille p
            LEFT JOIN carte_virtuelle c ON c.portefeuille_id = p.id
            WHERE p.user_id = :uid';

        $params = ['uid' => $id];
        if ($search !== '') {
            $sql .= ' AND (
                p.nom LIKE :q
                OR p.devise_principale LIKE :q
                OR CAST(p.id AS CHAR) LIKE :q
            )';
            $params['q'] = '%' . $search . '%';
        }

        $sql .= ' GROUP BY p.id, p.nom, p.solde_total, p.devise_principale, p.created_at, p.updated_at';
        $sql .= sprintf(' ORDER BY %s %s', $sortColumn, $direction);

        $wallets = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        $stats = $entityManager->getConnection()->fetchAssociative(
            'SELECT
                COUNT(*) AS total_wallets,
                COALESCE(SUM(p.solde_total), 0) AS total_balance,
                COUNT(c.id) AS total_cards,
                COALESCE(SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_cards
             FROM portefeuille p
             LEFT JOIN carte_virtuelle c ON c.portefeuille_id = p.id
             WHERE p.user_id = :uid',
            ['uid' => $id]
        ) ?: [];

        return $this->render('backoffice/wallet/user_wallets.html.twig', [
            'user' => $user,
            'wallets' => $wallets,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'stats' => [
                'totalWallets' => (int) ($stats['total_wallets'] ?? 0),
                'totalBalance' => (float) ($stats['total_balance'] ?? 0),
                'totalCards' => (int) ($stats['total_cards'] ?? 0),
                'activeCards' => (int) ($stats['active_cards'] ?? 0),
            ],
        ]);
    }

    #[Route('/{id}', name: 'wallet_cards', methods: ['GET'])]
    public function walletCards(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $wallet = $entityManager->getConnection()->fetchAssociative(
            'SELECT
                p.id,
                p.nom,
                p.solde_total,
                p.devise_principale,
                p.created_at,
                p.updated_at,
                u.id AS owner_id,
                u.full_name AS owner_name,
                u.email AS owner_email
             FROM portefeuille p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.id = :id',
            ['id' => $id]
        );

        if (!$wallet) {
            throw $this->createNotFoundException('Portefeuille introuvable.');
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = strtolower((string) $request->query->get('sort', 'id'));
        $direction = strtolower((string) $request->query->get('direction', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'c.id',
            'number' => 'c.numero_carte',
            'type' => 'c.type',
            'currency' => 'c.devise',
            'balance' => 'c.solde',
            'limit' => 'c.plafond',
            'status' => 'c.is_active',
            'updated_at' => 'c.updated_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'c.id';

        $sql = 'SELECT
                c.id,
                c.numero_carte,
                c.type,
                c.devise,
                c.solde,
                c.plafond,
                c.is_active,
                c.date_expiration,
                c.created_at,
                c.updated_at
            FROM carte_virtuelle c
            WHERE c.portefeuille_id = :pid';

        $params = ['pid' => $id];
        if ($search !== '') {
            $sql .= ' AND (
                c.numero_carte LIKE :q
                OR c.type LIKE :q
                OR c.devise LIKE :q
                OR CAST(c.id AS CHAR) LIKE :q
            )';
            $params['q'] = '%' . $search . '%';
        }

        $sql .= sprintf(' ORDER BY %s %s', $sortColumn, $direction);
        $cards = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        $stats = $entityManager->getConnection()->fetchAssociative(
            'SELECT
                COUNT(*) AS total_cards,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_cards,
                COALESCE(SUM(solde), 0) AS total_solde,
                COALESCE(MAX(updated_at), NULL) AS last_update
             FROM carte_virtuelle
             WHERE portefeuille_id = :pid',
            ['pid' => $id]
        ) ?: [];

        return $this->render('backoffice/wallet/wallet_cards.html.twig', [
            'wallet' => $wallet,
            'cards' => $cards,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'stats' => [
                'totalCards' => (int) ($stats['total_cards'] ?? 0),
                'activeCards' => (int) ($stats['active_cards'] ?? 0),
                'totalSolde' => (float) ($stats['total_solde'] ?? 0),
                'lastUpdate' => $stats['last_update'] ?? null,
            ],
        ]);
    }

    #[Route('/card/{id}/toggle', name: 'card_toggle', methods: ['POST'])]
    public function toggleCard(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_toggle_card_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_wallet_users');
        }

        $card = $entityManager->getConnection()->fetchAssociative(
            'SELECT id, portefeuille_id, is_active FROM carte_virtuelle WHERE id = :id',
            ['id' => $id]
        );

        if (!$card) {
            throw $this->createNotFoundException('Carte introuvable.');
        }

        $nextStatus = ((int) $card['is_active']) === 1 ? 0 : 1;

        $entityManager->getConnection()->update('carte_virtuelle', [
            'is_active' => $nextStatus,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $this->addFlash('success', $nextStatus === 1 ? 'Carte activée.' : 'Carte désactivée.');

        return $this->redirectToRoute('admin_wallet_wallet_cards', ['id' => (int) $card['portefeuille_id']]);
    }

    #[Route('/card/{id}/edit', name: 'card_edit', methods: ['GET', 'POST'])]
    public function editCard(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $card = $entityManager->getConnection()->fetchAssociative(
            'SELECT c.*, p.nom AS wallet_name
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE c.id = :id',
            ['id' => $id]
        );

        if (!$card) {
            throw $this->createNotFoundException('Carte introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_edit_card_' . $id, $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_wallet_card_edit', ['id' => $id]);
            }

            $type = strtoupper(trim((string) $request->request->get('type', '')));
            $devise = strtoupper(trim((string) $request->request->get('devise', '')));
            $plafond = trim((string) $request->request->get('plafond', '0'));
            $solde = trim((string) $request->request->get('solde', '0'));
            $expiration = trim((string) $request->request->get('date_expiration', ''));
            $isActive = $request->request->getBoolean('is_active');

            $errors = [];
            if (!in_array($type, ['NORMAL', 'SILVER', 'GOLD'], true)) {
                $errors[] = 'Type de carte invalide.';
            }
            if (!in_array($devise, ['TND', 'EUR', 'USD'], true)) {
                $errors[] = 'Devise invalide.';
            }
            if (!is_numeric($plafond) || (float) $plafond < 0) {
                $errors[] = 'Le plafond doit etre un nombre positif.';
            }
            if (!is_numeric($solde)) {
                $errors[] = 'Le solde doit etre un nombre valide.';
            }

            $expirationDate = null;
            if ($expiration !== '') {
                $expirationDate = \DateTimeImmutable::createFromFormat('Y-m-d', $expiration) ?: null;
                if (!$expirationDate) {
                    $errors[] = 'Date d expiration invalide.';
                }
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('form_error', $error);
                }
            } else {
                $entityManager->getConnection()->update('carte_virtuelle', [
                    'type' => $type,
                    'devise' => $devise,
                    'plafond' => number_format((float) $plafond, 2, '.', ''),
                    'solde' => number_format((float) $solde, 2, '.', ''),
                    'date_expiration' => $expirationDate?->format('Y-m-d'),
                    'is_active' => $isActive ? 1 : 0,
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $id]);

                $this->addFlash('success', 'Carte mise a jour avec succes.');

                return $this->redirectToRoute('admin_wallet_wallet_cards', [
                    'id' => (int) $card['portefeuille_id'],
                ]);
            }

            $card = $entityManager->getConnection()->fetchAssociative(
                'SELECT c.*, p.nom AS wallet_name
                 FROM carte_virtuelle c
                 INNER JOIN portefeuille p ON p.id = c.portefeuille_id
                 WHERE c.id = :id',
                ['id' => $id]
            );
        }

        return $this->render('backoffice/wallet/card_edit.html.twig', [
            'card' => $card,
        ]);
    }

    #[Route('/fees', name: 'fees_list', methods: ['GET'])]
    public function feesList(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $dateFrom = trim((string) $request->query->get('date_from', ''));
        $dateTo = trim((string) $request->query->get('date_to', ''));
        $userId = trim((string) $request->query->get('user_id', ''));
        $cardId = trim((string) $request->query->get('card_id', ''));
        $feeRate = trim((string) $request->query->get('fee_rate', ''));
        $sort = strtolower((string) $request->query->get('sort', 'created_at'));
        $direction = strtolower((string) $request->query->get('direction', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'tfe.id',
            'amount' => 'tfe.amount',
            'fee' => 'tfe.fee_amount',
            'rate' => 'tfe.applied_fee_rate',
            'window' => 'tfe.transfer_count_in_window',
            'user' => 'u.full_name',
            'card' => 'sc.numero_carte',
            'created_at' => 'tfe.created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'tfe.created_at';

        $sql = 'SELECT
                tfe.id,
                tfe.transaction_id,
                tfe.virement_programme_id,
                tfe.amount,
                tfe.currency,
                tfe.base_fee_rate,
                tfe.applied_fee_rate,
                tfe.fee_amount,
                tfe.transfer_count_in_window,
                tfe.rule_name,
                tfe.created_at,
                sc.numero_carte AS source_card,
                sc.devise AS source_devise,
                sc.id AS source_card_id,
                dc.numero_carte AS dest_card,
                dc.devise AS dest_devise,
                p1.nom AS source_wallet,
                p2.nom AS dest_wallet,
                u.id AS user_id,
                u.full_name AS user_name,
                u.email AS user_email
            FROM transfer_fee_event tfe
            INNER JOIN carte_virtuelle sc ON sc.id = tfe.source_card_id
            INNER JOIN carte_virtuelle dc ON dc.id = tfe.dest_card_id
            INNER JOIN portefeuille p1 ON p1.id = sc.portefeuille_id
            INNER JOIN portefeuille p2 ON p2.id = dc.portefeuille_id
            INNER JOIN users u ON u.id = p1.user_id
            WHERE 1=1';

        $params = [];

        if ($dateFrom !== '') {
            $sql .= ' AND DATE(tfe.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= ' AND DATE(tfe.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        if ($userId !== '' && is_numeric($userId)) {
            $sql .= ' AND u.id = :user_id';
            $params['user_id'] = (int) $userId;
        }

        if ($cardId !== '' && is_numeric($cardId)) {
            $sql .= ' AND (tfe.source_card_id = :card_id OR tfe.dest_card_id = :card_id)';
            $params['card_id'] = (int) $cardId;
        }

        if ($feeRate !== '') {
            if ($feeRate === '0.02') {
                $sql .= ' AND tfe.applied_fee_rate = 0.02000';
            } elseif ($feeRate === '0.01') {
                $sql .= ' AND tfe.applied_fee_rate = 0.01000';
            }
        }

        $sql .= sprintf(' ORDER BY %s %s', $sortColumn, $direction);

        $fees = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        // Get statistics
        $statsSql = 'SELECT
                COUNT(*) AS total_fees,
                COALESCE(SUM(tfe.amount), 0) AS total_amount,
                COALESCE(SUM(tfe.fee_amount), 0) AS total_fee_collected,
                COALESCE(AVG(tfe.applied_fee_rate), 0) AS avg_fee_rate,
                COUNT(CASE WHEN tfe.applied_fee_rate = 0.01000 THEN 1 END) AS discounted_count
            FROM transfer_fee_event tfe
            INNER JOIN carte_virtuelle sc ON sc.id = tfe.source_card_id
            INNER JOIN portefeuille p1 ON p1.id = sc.portefeuille_id
            INNER JOIN users u ON u.id = p1.user_id
            WHERE 1=1';

        $statsParams = [];

        if ($dateFrom !== '') {
            $statsSql .= ' AND DATE(tfe.created_at) >= :date_from';
            $statsParams['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $statsSql .= ' AND DATE(tfe.created_at) <= :date_to';
            $statsParams['date_to'] = $dateTo;
        }

        if ($userId !== '' && is_numeric($userId)) {
            $statsSql .= ' AND u.id = :user_id';
            $statsParams['user_id'] = (int) $userId;
        }

        if ($cardId !== '' && is_numeric($cardId)) {
            $statsSql .= ' AND (tfe.source_card_id = :card_id OR tfe.dest_card_id = :card_id)';
            $statsParams['card_id'] = (int) $cardId;
        }

        if ($feeRate !== '') {
            if ($feeRate === '0.02') {
                $statsSql .= ' AND tfe.applied_fee_rate = 0.02000';
            } elseif ($feeRate === '0.01') {
                $statsSql .= ' AND tfe.applied_fee_rate = 0.01000';
            }
        }

        $stats = $entityManager->getConnection()->fetchAssociative($statsSql, $statsParams) ?: [];

        // Get unique users for dropdown filter
        $allUsers = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT u.id, u.full_name, u.email
             FROM users u
             INNER JOIN portefeuille p ON p.user_id = u.id
             INNER JOIN carte_virtuelle c ON c.portefeuille_id = p.id
             INNER JOIN transfer_fee_event tfe ON tfe.source_card_id = c.id OR tfe.dest_card_id = c.id
             ORDER BY u.full_name ASC'
        );

        // Get unique cards for dropdown filter
        $allCards = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT c.id, c.numero_carte, c.devise, p.nom AS wallet_name
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             INNER JOIN transfer_fee_event tfe ON tfe.source_card_id = c.id OR tfe.dest_card_id = c.id
             ORDER BY c.numero_carte ASC'
        );

        return $this->render('backoffice/wallet/fees.html.twig', [
            'fees' => $fees,
            'allUsers' => $allUsers,
            'allCards' => $allCards,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'userId' => $userId,
            'cardId' => $cardId,
            'feeRate' => $feeRate,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'stats' => [
                'totalFees' => (int) ($stats['total_fees'] ?? 0),
                'totalAmount' => (float) ($stats['total_amount'] ?? 0),
                'totalFeeCollected' => (float) ($stats['total_fee_collected'] ?? 0),
                'avgFeeRate' => (float) ($stats['avg_fee_rate'] ?? 0),
                'discountedCount' => (int) ($stats['discounted_count'] ?? 0),
            ],
        ]);
    }
}
