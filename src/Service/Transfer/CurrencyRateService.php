<?php

namespace App\Service\Transfer;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CurrencyRateService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private Connection $connection,
        private string $fcsApiKey,
        private int $cacheTtlSeconds = 300,
    ) {
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === $to) {
            return round($amount, 2);
        }

        $rate = $this->getRate($from, $to);

        return round($amount * $rate, 2);
    }

    /**
     * @return array{updatedAt:string,rows:array<int,array<string,float|string>>}
     */
    public function getExchangeSnapshot(): array
    {
        $response = $this->httpClient->request('GET', 'https://open.er-api.com/v6/latest/USD', [
            'timeout' => 8,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Unable to fetch live exchange snapshot.');
        }

        $data = $response->toArray(false);
        $rates = $data['rates'] ?? null;

        if (!is_array($rates)) {
            throw new \RuntimeException('Invalid exchange snapshot payload.');
        }

        $usdToEur = isset($rates['EUR']) && is_numeric($rates['EUR']) ? (float) $rates['EUR'] : null;
        $usdToTnd = isset($rates['TND']) && is_numeric($rates['TND']) ? (float) $rates['TND'] : null;

        if ($usdToEur === null || $usdToEur <= 0 || $usdToTnd === null || $usdToTnd <= 0) {
            throw new \RuntimeException('Missing USD/EUR/TND rates in live snapshot.');
        }

        $tndToUsd = 1 / $usdToTnd;
        $tndToEur = $usdToEur / $usdToTnd;
        $eurToUsd = 1 / $usdToEur;
        $eurToTnd = $usdToTnd / $usdToEur;

        return [
            'updatedAt' => (string) ($data['time_last_update_utc'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s')),
            'rows' => [
                [
                    'currency' => 'TND',
                    'to_usd' => round($tndToUsd, 6),
                    'to_eur' => round($tndToEur, 6),
                ],
                [
                    'currency' => 'USD',
                    'to_tnd' => round($usdToTnd, 6),
                    'to_eur' => round($usdToEur, 6),
                ],
                [
                    'currency' => 'EUR',
                    'to_tnd' => round($eurToTnd, 6),
                    'to_usd' => round($eurToUsd, 6),
                ],
            ],
        ];
    }

    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === $to) {
            return 1.0;
        }

        if ($this->cacheTtlSeconds > 0) {
            $cached = $this->connection->fetchAssociative(
                'SELECT rate FROM exchange_rate_cache
                 WHERE provider = :provider
                   AND base_currency = :base
                   AND quote_currency = :quote
                   AND expires_at > NOW()'
                ,
                [
                    'provider' => 'FCSAPI',
                    'base' => $from,
                    'quote' => $to,
                ]
            );

            if ($cached && isset($cached['rate'])) {
                return (float) $cached['rate'];
            }
        }

        $rate = $this->fetchRateFromProvider($from, $to);

        if ($this->cacheTtlSeconds > 0) {
            $expiresAt = (new \DateTimeImmutable('+' . $this->cacheTtlSeconds . ' seconds'))->format('Y-m-d H:i:s');

            $this->connection->executeStatement(
                'INSERT INTO exchange_rate_cache (provider, base_currency, quote_currency, rate, fetched_at, expires_at)
                 VALUES (:provider, :base, :quote, :rate, NOW(), :expires)
                 ON DUPLICATE KEY UPDATE rate = VALUES(rate), fetched_at = NOW(), expires_at = VALUES(expires_at)',
                [
                    'provider' => 'FCSAPI',
                    'base' => $from,
                    'quote' => $to,
                    'rate' => $rate,
                    'expires' => $expiresAt,
                ]
            );
        }

        return $rate;
    }

    private function fetchRateFromProvider(string $from, string $to): float
    {
        if ($this->fcsApiKey === '') {
            return $this->fetchRateFromFallback($from, $to);
        }

        $pairSlash = $from . '/' . $to;
        $pairCompact = $from . $to;

        $urls = [
            sprintf('https://fcsapi.com/api-v3/forex/latest?symbol=%s&access_key=%s', urlencode($pairSlash), urlencode($this->fcsApiKey)),
            sprintf('https://fcsapi.com/api-v3/forex/latest?symbol=%s&access_key=%s', urlencode($pairCompact), urlencode($this->fcsApiKey)),
        ];

        foreach ($urls as $url) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 8,
                ]);

                if ($response->getStatusCode() !== 200) {
                    continue;
                }

                $data = $response->toArray(false);
                $rate = $this->extractRate($data);

                if ($rate !== null && $rate > 0) {
                    return $rate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->fetchRateFromFallback($from, $to);
    }

    private function fetchRateFromFallback(string $from, string $to): float
    {
        try {
            $response = $this->httpClient->request('GET', 'https://open.er-api.com/v6/latest/' . $from, [
                'timeout' => 8,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Fallback API failed with status ' . $response->getStatusCode());
            }

            $data = $response->toArray(false);
            $rates = $data['rates'] ?? null;

            if (is_array($rates) && isset($rates[$to]) && is_numeric($rates[$to])) {
                return (float) $rates[$to];
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Unable to fetch FX rate for %s/%s from both FCS and Fallback API. Error: %s', $from, $to, $e->getMessage()));
        }

        throw new \RuntimeException(sprintf('Unable to fetch FX rate for %s/%s from both FCS and Fallback API.', $from, $to));
    }

    private function extractRate(array $data): ?float
    {
        if (isset($data['response'][0]['c']) && is_numeric($data['response'][0]['c'])) {
            return (float) $data['response'][0]['c'];
        }

        if (isset($data['response'][0]['price']) && is_numeric($data['response'][0]['price'])) {
            return (float) $data['response'][0]['price'];
        }

        if (isset($data['response']['c']) && is_numeric($data['response']['c'])) {
            return (float) $data['response']['c'];
        }

        if (isset($data['result'][0]['price']) && is_numeric($data['result'][0]['price'])) {
            return (float) $data['result'][0]['price'];
        }

        if (isset($data['price']) && is_numeric($data['price'])) {
            return (float) $data['price'];
        }

        return null;
    }
}
