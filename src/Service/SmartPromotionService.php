<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;

class SmartPromotionService
{
    private const DEFAULT_PERIOD_DAYS = 1;
    private const DEFAULT_MIN_REDUCTION = 10.0;
    private const DEFAULT_MAX_REDUCTION = 50.0;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{rules: array<string, mixed>, total: int, products: array<int, array<string, mixed>>}
     */
    public function preview(
        User $user,
        int $periodDays = self::DEFAULT_PERIOD_DAYS,
        float $minReduction = self::DEFAULT_MIN_REDUCTION,
        float $maxReduction = self::DEFAULT_MAX_REDUCTION,
        array $productIds = []
    ): array {
        [$periodDays, $minReduction, $maxReduction] = $this->normalizeRules($periodDays, $minReduction, $maxReduction);

        $eligibleProducts = $this->findEligibleProducts($user->getId(), $periodDays, $productIds);
        $stockByType = $this->getAvailableStockByType($user->getId());

        $products = [];
        foreach ($eligibleProducts as $product) {
            $type = (string) ($product['type_produit'] ?? '');
            $stockAvailable = (int) ($stockByType[$type] ?? 0);
            $daysWithoutSale = (int) ($product['days_without_sale'] ?? 0);
            $currentPrice = (float) ($product['montant'] ?? 0);

            $reduction = $this->computeReduction(
                $daysWithoutSale,
                $stockAvailable,
                $type,
                $periodDays,
                $minReduction,
                $maxReduction
            );

            $newPrice = round($currentPrice * (1 - ($reduction / 100)), 2);

            $products[] = [
                'id' => (int) $product['id_produit'],
                'nom' => (string) ($product['nom_produit'] ?? ''),
                'categorie' => $type,
                'stock_disponible' => $stockAvailable,
                'jours_sans_vente' => $daysWithoutSale,
                'prix_actuel' => $currentPrice,
                'reduction_pourcentage' => $reduction,
                'nouveau_prix' => $newPrice,
            ];
        }

        return [
            'rules' => [
                'period_days' => $periodDays,
                'min_reduction' => $minReduction,
                'max_reduction' => $maxReduction,
            ],
            'total' => count($products),
            'products' => $products,
        ];
    }

    /**
     * @return array{rules: array<string, mixed>, total: int, updated_count: int, skipped_count: int, products: array<int, array<string, mixed>>}
     */
    public function apply(
        User $user,
        int $periodDays = self::DEFAULT_PERIOD_DAYS,
        float $minReduction = self::DEFAULT_MIN_REDUCTION,
        float $maxReduction = self::DEFAULT_MAX_REDUCTION,
        array $productIds = []
    ): array {
        $preview = $this->preview($user, $periodDays, $minReduction, $maxReduction, $productIds);

        if ($preview['total'] === 0) {
            return [
                'rules' => $preview['rules'],
                'total' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'products' => [],
            ];
        }

        $updatedCount = 0;
        $skippedCount = 0;

        $this->connection->beginTransaction();
        try {
            foreach ($preview['products'] as &$product) {
                $id = (int) ($product['id'] ?? 0);
                $newPrice = (float) ($product['nouveau_prix'] ?? 0);

                if ($id <= 0 || $newPrice <= 0) {
                    ++$skippedCount;
                    $product['applied'] = false;
                    continue;
                }

                $affectedRows = $this->connection->update('produit', [
                    'montant' => number_format($newPrice, 2, '.', ''),
                ], [
                    'id_produit' => $id,
                    'user_id' => $user->getId(),
                    'statut' => 'disponible',
                ]);

                $applied = $affectedRows > 0;
                if ($applied) {
                    ++$updatedCount;
                } else {
                    ++$skippedCount;
                }

                $product['applied'] = $applied;
            }
            unset($product);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return [
            'rules' => $preview['rules'],
            'total' => $preview['total'],
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'products' => $preview['products'],
        ];
    }

    private function computeReduction(
        int $daysWithoutSale,
        int $stockAvailable,
        string $typeProduit,
        int $periodDays,
        float $minReduction,
        float $maxReduction
    ): float {
        $ageWindow = max(1, 120 - $periodDays);
        $ageScore = max(0.0, min(1.0, ($daysWithoutSale - $periodDays) / $ageWindow));

        // Stock interpreted as number of available products in same category/type.
        $stockScore = max(0.0, min(1.0, $stockAvailable / 10));

        $categoryScore = match ($typeProduit) {
            'carte_abonnement' => 0.3,
            'carte_cadeaux' => 0.6,
            'carte_prepaye' => 1.0,
            default => 0.5,
        };

        $globalScore = (0.55 * $ageScore) + (0.30 * $stockScore) + (0.15 * $categoryScore);
        $reduction = $minReduction + (($maxReduction - $minReduction) * $globalScore);

        return round(max($minReduction, min($maxReduction, $reduction)), 2);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findEligibleProducts(int $userId, int $periodDays, array $productIds = []): array
    {
        $sql = "
            SELECT
                p.id_produit,
                p.nom_produit,
                p.montant,
                p.type_produit,
                p.date_creation,
                COALESCE(MAX(CASE WHEN f.statut = 'payee' THEN f.date_facture END), DATE(p.date_creation)) AS last_sale_date,
                DATEDIFF(CURDATE(), COALESCE(MAX(CASE WHEN f.statut = 'payee' THEN f.date_facture END), DATE(p.date_creation))) AS days_without_sale
            FROM produit p
            LEFT JOIN facture f ON f.id_produit = p.id_produit AND f.user_id = :user_id
            WHERE p.user_id = :user_id
              AND p.statut = 'disponible'
        ";

        $params = ['user_id' => $userId, 'period_days' => $periodDays];

        if ($productIds !== []) {
            $ids = array_values(array_unique(array_map('intval', $productIds)));
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

            if ($ids === []) {
                return [];
            }

            $placeholders = [];
            foreach ($ids as $index => $id) {
                $key = 'pid_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }

            $sql .= ' AND p.id_produit IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= "
            GROUP BY p.id_produit, p.nom_produit, p.montant, p.type_produit, p.date_creation
            HAVING days_without_sale >= 0
            ORDER BY days_without_sale DESC, p.id_produit ASC
        ";

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    /**
     * @return array<string, int>
     */
    private function getAvailableStockByType(int $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "
            SELECT type_produit, COUNT(*) AS stock_available
            FROM produit
            WHERE user_id = :user_id AND statut = 'disponible'
            GROUP BY type_produit
            ",
            ['user_id' => $userId]
        );

        $result = [];
        foreach ($rows as $row) {
            $type = (string) ($row['type_produit'] ?? '');
            $result[$type] = (int) ($row['stock_available'] ?? 0);
        }

        return $result;
    }

    /**
     * @return array{0:int,1:float,2:float}
     */
    private function normalizeRules(int $periodDays, float $minReduction, float $maxReduction): array
    {
        $periodDays = max(1, min(365, $periodDays));
        $minReduction = max(0.0, min(50.0, $minReduction));
        $maxReduction = max($minReduction, min(50.0, $maxReduction));

        return [$periodDays, $minReduction, $maxReduction];
    }
}
