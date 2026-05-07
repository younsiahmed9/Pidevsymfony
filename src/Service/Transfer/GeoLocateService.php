<?php

namespace App\Service\Transfer;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeoLocateService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiLocateApiKey,
    ) {
    }

    /**
     * @return array{country_code:?string,country_name:?string,city:?string,latitude:?float,longitude:?float,risk_score:int,decision:string,provider:string}
     */
    public function locate(?string $ipAddress): array
    {
        if ($ipAddress === null || trim($ipAddress) === '') {
            return $this->emptyResult('No IP address');
        }

        $ip = trim($ipAddress);

        if (!$this->isPublicIp($ip)) {
            return $this->emptyResult('Non-public IP');
        }

        $providers = [];

        if ($this->apiLocateApiKey !== '') {
            $providers[] = [
                'name' => 'IPLOCATE',
                'url' => sprintf('https://iplocate.io/api/lookup/%s?apikey=%s', urlencode($ip), urlencode($this->apiLocateApiKey)),
            ];
        }

        $providers[] = [
            'name' => 'IPAPI',
            'url' => sprintf('https://ipapi.co/%s/json/', urlencode($ip)),
        ];
        $providers[] = [
            'name' => 'IPWHOIS',
            'url' => sprintf('https://ipwho.is/%s', urlencode($ip)),
        ];

        $lastError = 'Lookup failed';

        foreach ($providers as $provider) {
            $providerName = (string) ($provider['name'] ?? 'UNKNOWN');
            $url = (string) ($provider['url'] ?? '');

            if ($url === '') {
                continue;
            }

            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 8,
                ]);

                if ($response->getStatusCode() !== 200) {
                    $lastError = $providerName . ':HTTP ' . $response->getStatusCode();
                    continue;
                }

                $data = $response->toArray(false);
                $parsed = $this->extract($data, $providerName);

                if ($parsed['country_code'] !== null || $parsed['country_name'] !== null) {
                    return $parsed;
                }

                $lastError = $providerName . ':Empty payload';
            } catch (\Throwable $e) {
                $lastError = $providerName . ':' . mb_substr($e->getMessage(), 0, 120);
                continue;
            }
        }

        return $this->emptyResult($lastError);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @return array{country_code:?string,country_name:?string,city:?string,latitude:?float,longitude:?float,risk_score:int,decision:string,provider:string}
     */
    private function extract(array $data, string $provider): array
    {
        $countryCode = $data['country_code'] ?? $data['countryCode'] ?? $data['country_code_iso3'] ?? null;
        $countryName = $data['country_name'] ?? $data['countryName'] ?? $data['country'] ?? null;
        $city = $data['city'] ?? null;
        $latitude = $data['latitude'] ?? $data['lat'] ?? null;
        $longitude = $data['longitude'] ?? $data['lon'] ?? $data['lng'] ?? null;

        return [
            'country_code' => is_string($countryCode) ? strtoupper($countryCode) : null,
            'country_name' => is_string($countryName) ? $countryName : null,
            'city' => is_string($city) ? $city : null,
            'latitude' => is_numeric($latitude) ? (float) $latitude : null,
            'longitude' => is_numeric($longitude) ? (float) $longitude : null,
            'risk_score' => 0,
            'decision' => 'ALLOW',
            'provider' => $provider,
        ];
    }

    /**
     * @return array{country_code:?string,country_name:?string,city:?string,latitude:?float,longitude:?float,risk_score:int,decision:string,provider:string}
     */
    private function emptyResult(string $reason): array
    {
        return [
            'country_code' => null,
            'country_name' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
            'risk_score' => 0,
            'decision' => 'REVIEW',
            'provider' => 'IPLOCATE:' . $reason,
        ];
    }
}
