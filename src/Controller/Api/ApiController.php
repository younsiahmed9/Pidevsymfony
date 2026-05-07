<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Promotion\VoucherifyPromotionService;
use App\Service\Promotion\WhatsAppPromotionNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    #[Route('/budgets', name: 'budgets', methods: ['GET'])]
    public function getBudgets(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_budget, nom_budget, montant_total, periode, statut, date_creation,
                    (SELECT COALESCE(SUM(montant), 0) FROM depense WHERE id_budget = budget.id_budget) as total_depense
             FROM budget
             WHERE user_id = :user_id
             ORDER BY id_budget DESC',
            ['user_id' => $user->getId()]
        );

        return $this->json($budgets);
    }

    #[Route('/budgets/{id}', name: 'budget_detail', methods: ['GET'])]
    public function getBudgetDetail(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budget = $entityManager->getConnection()->fetchAssociative(
            'SELECT b.*, 
                    (SELECT COALESCE(SUM(montant), 0) FROM depense WHERE id_budget = b.id_budget) as total_depense,
                    (SELECT COALESCE(COUNT(*), 0) FROM depense WHERE id_budget = b.id_budget) as nb_depenses
             FROM budget b
             WHERE b.id_budget = :id AND b.user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$budget) {
            return $this->json(['error' => 'Budget non trouvé'], 404);
        }

        return $this->json($budget);
    }

    #[Route('/budgets', name: 'budget_create', methods: ['POST'])]
    public function createBudget(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['nom_budget'], $data['montant_total'], $data['periode'])) {
            return $this->json(['error' => 'Données incomplètes'], 400);
        }

        $conn = $entityManager->getConnection();
        $conn->insert('budget', [
            'user_id' => $user->getId(),
            'nom_budget' => $data['nom_budget'],
            'montant_total' => $data['montant_total'],
            'periode' => $data['periode'],
            'statut' => $data['statut'] ?? 'actif',
            'date_creation' => date('Y-m-d H:i:s'),
        ]);

        return $this->json(['success' => true, 'id' => $conn->lastInsertId()], 201);
    }

    #[Route('/budgets/{id}', name: 'budget_update', methods: ['PUT'])]
    public function updateBudget(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budget = $entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM budget WHERE id_budget = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$budget) {
            return $this->json(['error' => 'Budget non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $updates = [];

        if (isset($data['nom_budget'])) $updates['nom_budget'] = $data['nom_budget'];
        if (isset($data['montant_total'])) $updates['montant_total'] = $data['montant_total'];
        if (isset($data['periode'])) $updates['periode'] = $data['periode'];
        if (isset($data['statut'])) $updates['statut'] = $data['statut'];

        if ($updates) {
            $entityManager->getConnection()->update('budget', $updates, ['id_budget' => $id]);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/budgets/{id}', name: 'budget_delete', methods: ['DELETE'])]
    public function deleteBudget(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budget = $entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM budget WHERE id_budget = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$budget) {
            return $this->json(['error' => 'Budget non trouvé'], 404);
        }

        $entityManager->getConnection()->delete('budget', ['id_budget' => $id]);

        return $this->json(['success' => true]);
    }

    #[Route('/depenses', name: 'depenses', methods: ['GET'])]
    public function getDepenses(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $depenses = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT d.*, b.nom_budget
             FROM depense d
             LEFT JOIN budget b ON b.id_budget = d.id_budget
             WHERE d.user_id = :user_id
             ORDER BY d.date_depense DESC',
            ['user_id' => $user->getId()]
        );

        return $this->json($depenses);
    }

    #[Route('/depenses/{id}', name: 'depense_detail', methods: ['GET'])]
    public function getDepenseDetail(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $depense = $entityManager->getConnection()->fetchAssociative(
            'SELECT d.*, b.nom_budget
             FROM depense d
             LEFT JOIN budget b ON b.id_budget = d.id_budget
             WHERE d.id_depense = :id AND d.user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$depense) {
            return $this->json(['error' => 'Dépense non trouvée'], 404);
        }

        return $this->json($depense);
    }

    #[Route('/depenses', name: 'depense_create', methods: ['POST'])]
    public function createDepense(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['montant'], $data['id_budget'])) {
            return $this->json(['error' => 'Données incomplètes'], 400);
        }

        $conn = $entityManager->getConnection();
        $conn->insert('depense', [
            'user_id' => $user->getId(),
            'id_budget' => $data['id_budget'],
            'montant' => $data['montant'],
            'description' => $data['description'] ?? null,
            'categorie' => $data['categorie'] ?? null,
            'date_depense' => $data['date_depense'] ?? date('Y-m-d H:i:s'),
        ]);

        return $this->json(['success' => true, 'id' => $conn->lastInsertId()], 201);
    }

    #[Route('/depenses/{id}', name: 'depense_update', methods: ['PUT'])]
    public function updateDepense(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $depense = $entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM depense WHERE id_depense = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$depense) {
            return $this->json(['error' => 'Dépense non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $updates = [];

        if (isset($data['montant'])) $updates['montant'] = $data['montant'];
        if (isset($data['description'])) $updates['description'] = $data['description'];
        if (isset($data['categorie'])) $updates['categorie'] = $data['categorie'];
        if (isset($data['date_depense'])) $updates['date_depense'] = $data['date_depense'];

        if ($updates) {
            $entityManager->getConnection()->update('depense', $updates, ['id_depense' => $id]);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/depenses/{id}', name: 'depense_delete', methods: ['DELETE'])]
    public function deleteDepense(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $depense = $entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM depense WHERE id_depense = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$depense) {
            return $this->json(['error' => 'Dépense non trouvée'], 404);
        }

        $entityManager->getConnection()->delete('depense', ['id_depense' => $id]);

        return $this->json(['success' => true]);
    }

    #[Route('/comptes', name: 'comptes', methods: ['GET'])]
    public function getComptes(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $comptes = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.numero_compte, c.type_compte, c.solde, c.etat, c.date_creation
             FROM compte c
             WHERE c.user_id = :user_id
             ORDER BY c.date_creation DESC',
            ['user_id' => $user->getId()]
        );

        return $this->json($comptes);
    }

    #[Route('/credits', name: 'credits', methods: ['GET'])]
    public function getCredits(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $credits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT cr.id, cr.montant, cr.taux_interet, cr.duree_mois, cr.mensualite, cr.status, cr.date_debut
             FROM credit cr
             INNER JOIN compte c ON c.id = cr.compte_id
             WHERE c.user_id = :user_id
             ORDER BY cr.date_debut DESC',
            ['user_id' => $user->getId()]
        );

        return $this->json($credits);
    }

    #[Route('/promotions/voucherify/validate', name: 'promotion_voucherify_validate', methods: ['POST'])]
    public function validateVoucherifyPromotion(
        Request $request,
        VoucherifyPromotionService $voucherifyPromotionService,
        WhatsAppPromotionNotifier $whatsAppPromotionNotifier
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['status' => 'error', 'message' => 'Non authentifie'], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $redeemableId = trim((string) ($data['redeemable_id'] ?? $data['redeemableId'] ?? ''));
        if ($redeemableId === '') {
            return $this->json(['status' => 'error', 'message' => 'Le code promo est obligatoire.'], 400);
        }

        $amountValue = $data['amount'] ?? null;
        $amount = is_numeric($amountValue) ? (int) round((float) $amountValue) : null;

        $currencyValue = $data['currency'] ?? null;
        $currency = is_string($currencyValue) && trim($currencyValue) !== '' ? trim($currencyValue) : null;

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $productIds = is_array($data['product_ids'] ?? null) ? $data['product_ids'] : [];

        $applyToProducts = false;
        if (array_key_exists('apply_to_products', $data)) {
            $applyToProducts = filter_var($data['apply_to_products'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if ($applyToProducts) {
            $result = $voucherifyPromotionService->validateAndApplyToAvailableProducts(
                $user,
                $redeemableId,
                $amount,
                $currency,
                $metadata,
                $productIds
            );
        } else {
            $validation = $voucherifyPromotionService->validateVoucher($user, $redeemableId, $amount, $currency, $metadata);
            $selectedCount = count(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0));

            $result = [
                'validation' => $validation,
                'discount_percent_applied' => 0.0,
                'updated_count' => 0,
                'selected_count' => $selectedCount,
            ];
        }

        $validation = is_array($result['validation'] ?? null) ? $result['validation'] : [];
        $discount = is_array($validation['discount'] ?? null) ? $validation['discount'] : [];
        $discountPercent = (float) ($result['discount_percent_applied'] ?? 0);
        $updatedCount = (int) ($result['updated_count'] ?? 0);
        $selectedCount = (int) ($result['selected_count'] ?? 0);
        $reason = (string) ($validation['reason'] ?? '');

        if (!$applyToProducts && array_key_exists('whatsapp_number', $data)) {
            $whatsappNumber = trim((string) $data['whatsapp_number']);

            if ($whatsappNumber === '') {
                return $this->json(['status' => 'error', 'message' => 'Le numéro WhatsApp est invalide.'], 400);
            }

            if (($validation['valid'] ?? false) !== true) {
                return $this->json([
                    'status' => 'warning',
                    'message' => $reason !== '' ? $reason : 'Promotion non valide.',
                    'data' => [
                        'validation' => $validation,
                        'sms_sent' => false,
                    ],
                ]);
            }

            $discountLabel = (string) ($discount['percent_off'] ?? $discount['amount_off'] ?? $discount['unit_off'] ?? $discountPercent);
            $message = sprintf(
                "Votre code promo Voucherify %s est valide. Réduction %s%s.",
                $redeemableId,
                $discountLabel !== '' ? $discountLabel : 'appliquée',
                $discountLabel !== '' && (string) ($discount['percent_off'] ?? '') !== '' ? '%' : ''
            );

            $sent = $whatsAppPromotionNotifier->sendPromotionMessage($whatsappNumber, $message);

            return $this->json([
                'status' => 'success',
                'message' => 'Message WhatsApp envoyé avec succès.',
                'data' => [
                    'validation' => $validation,
                    'sms_sent' => true,
                    'sms_message_id' => $sent['message_id'] ?? '',
                    'selected_count' => $selectedCount,
                    'updated_count' => $updatedCount,
                ],
            ]);
        }

        if (($validation['valid'] ?? false) !== true) {
            return $this->json([
                'status' => 'warning',
                'message' => $reason !== '' ? $reason : 'Promotion non valide.',
                'data' => $result,
            ]);
        }

        if ($applyToProducts && $updatedCount <= 0) {
            return $this->json([
                'status' => 'warning',
                'message' => $reason !== '' ? $reason : 'Validation faite, mais aucun produit n\'a ete mis a jour.',
                'data' => $result,
            ]);
        }

        return $this->json([
            'status' => 'success',
            'message' => $applyToProducts ? 'Coupon applique avec succes.' : 'Coupon valide.',
            'data' => $result,
        ]);
    }
}