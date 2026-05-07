<?php

namespace App\Service\Promotion;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class VoucherifyPromotionService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private Connection $connection,
        private string $apiUrl,
        private string $appId,
        private string $appToken,
        private string $channel,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validateVoucher(
        User $user,
        string $redeemableId,
        ?int $amount = null,
        ?string $currency = null,
        array $metadata = []
    ): array {
        if (trim($this->appId) === '' || trim($this->appToken) === '') {
            throw new \RuntimeException('Voucherify credentials are not configured.');
        }

        $redeemableId = trim($redeemableId);
        if ($redeemableId === '') {
            throw new \InvalidArgumentException('Voucher ID or code is required.');
        }

        $payload = [
            'redeemables' => [
                [
                    'object' => 'voucher',
                    'id' => $redeemableId,
                ],
            ],
            'customer' => array_filter([
                'source_id' => (string) ($user->getId() ?? ''),
                'email' => (string) ($user->getEmail() ?? ''),
            ], static fn (mixed $value): bool => $value !== '' && $value !== null),
        ];

        if ($amount !== null && $amount > 0) {
            $payload['order'] = [
                'amount' => $amount,
            ];

            if ($currency !== null && trim($currency) !== '') {
                $payload['order']['currency'] = strtoupper(trim($currency));
            }
        }

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $response = null;
        $lastTransportException = null;

        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $response = $this->httpClient->request('POST', rtrim($this->apiUrl, '/') . '/validations', [
                    'headers' => [
                        'x-app-id' => $this->appId,
                        'x-app-token' => $this->appToken,
                        'X-Voucherify-Channel' => $this->channel,
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                    ],
                    'json' => $payload,
                    'timeout' => 20,
                ]);

                $statusCode = $response->getStatusCode();
                $rawContent = trim($response->getContent(false));
                $data = [];

                if ($rawContent !== '') {
                    $decoded = json_decode($rawContent, true);
                    if (is_array($decoded)) {
                        $data = $decoded;
                    }
                }

                if ($statusCode >= 400) {
                    $apiMessage = (string) ($data['message'] ?? $data['error'] ?? $rawContent);
                    $message = trim($apiMessage) !== ''
                        ? sprintf('Voucherify request failed with status %d: %s', $statusCode, $apiMessage)
                        : sprintf('Voucherify request failed with status %d', $statusCode);

                    throw new \RuntimeException($message);
                }

                if ($data === []) {
                    $data = $response->toArray(false);
                }

                $discount = $this->extractDiscount($data);
                $reason = $this->extractReason($data);

                return [
                    'valid' => (bool) ($data['valid'] ?? false),
                    'redeemable_id' => (string) ($data['redeemables'][0]['id'] ?? $redeemableId),
                    'tracking_id' => (string) ($data['tracking_id'] ?? ''),
                    'reason' => $reason,
                    'discount' => [
                        'type' => (string) ($discount['type'] ?? ''),
                        'amount_off' => $discount['amount_off'] ?? null,
                        'percent_off' => $discount['percent_off'] ?? null,
                        'unit_off' => $discount['unit_off'] ?? null,
                    ],
                    'raw' => $data,
                ];
            } catch (TransportExceptionInterface $e) {
                $lastTransportException = $e;

                if ($attempt === 2) {
                    break;
                }
            }
        }

        if ($lastTransportException instanceof TransportExceptionInterface) {
            throw new \RuntimeException('Voucherify timeout reseau. Reessayez dans quelques secondes.', 0, $lastTransportException);
        }

        throw new \RuntimeException('Voucherify request failed due to an unknown network error.');
    }

    /**
     * @return array<string, mixed>
     */
    public function validateAndApplyToAvailableProducts(
        User $user,
        string $redeemableId,
        ?int $amount = null,
        ?string $currency = null,
        array $metadata = [],
        array $productIds = []
    ): array {
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0)));

        $validation = $this->validateVoucher($user, $redeemableId, $amount, $currency, $metadata);

        if (($validation['valid'] ?? false) !== true) {
            return [
                'validation' => $validation,
                'discount_percent_applied' => 0.0,
                'updated_count' => 0,
                'selected_count' => count($selectedIds),
            ];
        }

        $discountPercent = $this->resolveDiscountPercent($validation, $amount);
        if ($discountPercent <= 0.0) {
            return [
                'validation' => $validation,
                'discount_percent_applied' => 0.0,
                'updated_count' => 0,
                'selected_count' => count($selectedIds),
            ];
        }

        $sql = 'SELECT id_produit, montant FROM produit WHERE user_id = :user_id AND statut = :statut';
        $params = [
            'user_id' => $user->getId(),
            'statut' => 'disponible',
        ];

        if ($selectedIds !== []) {
            $placeholders = [];
            foreach ($selectedIds as $index => $id) {
                $key = 'pid_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }

            $sql .= ' AND id_produit IN (' . implode(', ', $placeholders) . ')';
        }

        $products = $this->connection->fetchAllAssociative($sql, $params);

        if ($products === []) {
            return [
                'validation' => $validation,
                'discount_percent_applied' => $discountPercent,
                'updated_count' => 0,
                'selected_count' => 0,
            ];
        }

        $updatedCount = 0;
        $selectedCount = count($products);
        $this->connection->beginTransaction();
        try {
            foreach ($products as $product) {
                $id = (int) ($product['id_produit'] ?? 0);
                $currentAmount = (float) ($product['montant'] ?? 0);

                if ($id <= 0 || $currentAmount <= 0) {
                    continue;
                }

                $newAmount = round($currentAmount * (1 - ($discountPercent / 100)), 2);
                $newAmount = max(0.01, $newAmount);

                $affectedRows = $this->connection->update('produit', [
                    'montant' => $newAmount,
                ], [
                    'id_produit' => $id,
                    'user_id' => $user->getId(),
                    'statut' => 'disponible',
                ]);

                if ($affectedRows > 0) {
                    ++$updatedCount;
                }
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return [
            'validation' => $validation,
            'discount_percent_applied' => $discountPercent,
            'updated_count' => $updatedCount,
            'selected_count' => $selectedCount,
        ];
    }

    private function resolveDiscountPercent(array $validation, ?int $amount): float
    {
        $discount = is_array($validation['discount'] ?? null) ? $validation['discount'] : [];

        $percentOff = (float) ($discount['percent_off'] ?? 0);
        if ($percentOff > 0) {
            return min(100.0, $percentOff);
        }

        $amountOff = (float) ($discount['amount_off'] ?? 0);
        if ($amountOff > 0 && $amount !== null && $amount > 0) {
            return min(100.0, ($amountOff / $amount) * 100);
        }

        $unitOff = (float) ($discount['unit_off'] ?? 0);
        if ($unitOff > 0 && $amount !== null && $amount > 0) {
            return min(100.0, ($unitOff / $amount) * 100);
        }

        $raw = is_array($validation['raw'] ?? null) ? $validation['raw'] : [];
        $order = is_array($raw['order'] ?? null) ? $raw['order'] : [];

        $discountAmount = (float) ($order['discount_amount'] ?? 0);
        if ($discountAmount > 0 && $amount !== null && $amount > 0) {
            return min(100.0, ($discountAmount / $amount) * 100);
        }

        $totalAmount = (float) ($order['total_amount'] ?? 0);
        $baseAmount = (float) ($order['amount'] ?? ($amount ?? 0));
        if ($baseAmount > 0 && $totalAmount > 0 && $totalAmount < $baseAmount) {
            return min(100.0, (($baseAmount - $totalAmount) / $baseAmount) * 100);
        }

        $nestedPercent = $this->findFirstNumericValue($validation, ['percent_off', 'percentage_off', 'discount_percent']);
        if ($nestedPercent > 0) {
            return min(100.0, $nestedPercent);
        }

        $nestedAmountOff = $this->findFirstNumericValue($validation, ['amount_off', 'discount_amount', 'amountDiscount']);
        if ($nestedAmountOff > 0 && $amount !== null && $amount > 0) {
            return min(100.0, ($nestedAmountOff / $amount) * 100);
        }

        return 0.0;
    }

    private function findFirstNumericValue(array $data, array $keys): float
    {
        foreach ($keys as $key) {
            $value = $this->findKeyRecursive($data, $key);
            if (is_numeric($value)) {
                $numericValue = (float) $value;
                if ($numericValue > 0) {
                    return $numericValue;
                }
            }
        }

        return 0.0;
    }

    private function findKeyRecursive(array $data, string $needle): mixed
    {
        foreach ($data as $key => $value) {
            if ($key === $needle) {
                return $value;
            }

            if (is_array($value)) {
                $found = $this->findKeyRecursive($value, $needle);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDiscount(array $data): array
    {
        $topLevelDiscount = $data['discount'] ?? null;
        if (is_array($topLevelDiscount)) {
            return $topLevelDiscount;
        }

        $redeemables = $data['redeemables'] ?? null;
        if (!is_array($redeemables)) {
            return [];
        }

        foreach ($redeemables as $redeemable) {
            if (!is_array($redeemable)) {
                continue;
            }

            $resultDiscount = $redeemable['result']['discount'] ?? null;
            if (is_array($resultDiscount)) {
                return $resultDiscount;
            }
        }

        return [];
    }

    private function extractReason(array $data): string
    {
        $reason = $data['reason'] ?? null;
        if (is_string($reason) && trim($reason) !== '') {
            return trim($reason);
        }

        $message = $data['message'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        $redeemables = $data['redeemables'] ?? null;
        if (is_array($redeemables)) {
            foreach ($redeemables as $redeemable) {
                if (!is_array($redeemable)) {
                    continue;
                }

                $candidates = [
                    $redeemable['result']['reason'] ?? null,
                    $redeemable['result']['message'] ?? null,
                    $redeemable['status'] ?? null,
                ];

                foreach ($candidates as $candidate) {
                    if (is_string($candidate) && trim($candidate) !== '') {
                        return trim($candidate);
                    }
                }
            }
        }

        return '';
    }
}
