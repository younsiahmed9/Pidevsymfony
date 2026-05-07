<?php

namespace App\Service\Promotion;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WhatsAppPromotionNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiUrl,
        private string $accessToken,
        private string $phoneNumberId,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiUrl) !== ''
            && trim($this->accessToken) !== ''
            && trim($this->phoneNumberId) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function sendPromotionMessage(string $toPhoneNumber, string $message): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('WhatsApp API is not configured.');
        }

        $to = $this->normalizePhoneNumber($toPhoneNumber);
        if ($to === '') {
            throw new \InvalidArgumentException('Numero WhatsApp invalide.');
        }

        $body = trim($message);
        if ($body === '') {
            throw new \InvalidArgumentException('Le message WhatsApp est vide.');
        }

        $endpoint = rtrim($this->apiUrl, '/') . '/' . trim($this->phoneNumberId) . '/messages';

        $response = $this->httpClient->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . trim($this->accessToken),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
            'json' => [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ],
            'timeout' => 12,
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
            $apiMessage = (string) ($data['error']['message'] ?? $data['message'] ?? $rawContent);
            $message = trim($apiMessage) !== ''
                ? sprintf('WhatsApp request failed with status %d: %s', $statusCode, $apiMessage)
                : sprintf('WhatsApp request failed with status %d', $statusCode);

            throw new \RuntimeException($message);
        }

        $messageId = (string) ($data['messages'][0]['id'] ?? '');

        return [
            'status_code' => $statusCode,
            'message_id' => $messageId,
            'to' => $to,
            'raw' => $data,
        ];
    }

    private function normalizePhoneNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '' || strlen($digits) < 8) {
            return '';
        }

        return $digits;
    }
}
