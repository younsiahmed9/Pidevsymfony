<?php

namespace App\Service\Invoice;

use App\Entity\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class InvoiceApiService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiUrl,
        private string $apiKey,
        private string $templateId,
    ) {
    }

    /**
     * @param array<string, mixed> $product
     */
    public function generateProductInvoicePdf(User $user, array $product): string
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('InvoiceAPI key is not configured.');
        }

        $productId = (int) ($product['id'] ?? 0);
        $productName = (string) ($product['nomProduit'] ?? 'Produit');
        $productCode = (string) ($product['codeUnique'] ?? '');
        $productType = (string) ($product['typeProduit'] ?? '');
        $amount = (float) ($product['montant'] ?? 0);

        if ($productId <= 0 || $amount <= 0) {
            throw new \RuntimeException('Invalid product data for invoice generation.');
        }

        $invoiceNumber = sprintf('INV-PRD-%d-%s', $productId, (new \DateTimeImmutable())->format('YmdHis'));
        $issueDate = (new \DateTimeImmutable())->format('Y-m-d');

        $payload = [
            'template_id' => $this->templateId !== '' ? $this->templateId : 'modern',
            'seller' => [
                'name' => 'FinTrack',
                'email' => 'support@fintrack.local',
                'address' => 'Tunis, Tunisia',
            ],
            'buyer' => [
                'name' => (string) ($user->getFullName() ?? 'Client FinTrack'),
                'email' => (string) ($user->getEmail() ?? ''),
            ],
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'currency' => 'TND',
            'line_items' => [
                [
                    'description' => trim(sprintf('%s (%s) %s', $productName, $productType, $productCode !== '' ? '- ' . $productCode : '')),
                    'quantity' => 1,
                    'unit_price' => round($amount, 2),
                ],
            ],
            'notes' => 'Facture generee automatiquement depuis FrontOffice FinTrack.',
            'payment_terms' => 'Paiement immediat',
        ];

        // First call creates the invoice on InvoiceAPI.
        $response = $this->httpClient->request('POST', rtrim($this->apiUrl, '/') . '/invoices', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/pdf',
            ],
            'json' => $payload,
            'timeout' => 20,
        ]);

        $statusCode = $response->getStatusCode();
        $contentType = strtolower((string) $response->getHeaders(false)['content-type'][0] ?? '');
        $content = $response->getContent(false);

        if ($statusCode >= 400) {
            $message = trim($content);
            if ($message === '') {
                $message = 'InvoiceAPI request failed with status ' . $statusCode;
            }

            throw new \RuntimeException($message);
        }

        // Some responses already contain the PDF, but others return JSON with a download URL.
        if (str_contains($contentType, 'application/pdf')) {
            return $content;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException('InvoiceAPI returned an unexpected non-PDF response.');
        }

        $downloadUrl = (string) ($data['download_url'] ?? '');
        if ($downloadUrl === '') {
            throw new \RuntimeException('InvoiceAPI did not return a download_url.');
        }

        if (!str_starts_with($downloadUrl, 'http://') && !str_starts_with($downloadUrl, 'https://')) {
            $baseHost = preg_replace('#/v1$#', '', rtrim($this->apiUrl, '/'));
            $downloadUrl = $baseHost . '/' . ltrim($downloadUrl, '/');
        }

        // Second call downloads the final PDF when InvoiceAPI returns a temporary link.
        $pdfResponse = $this->httpClient->request('GET', $downloadUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/pdf',
            ],
            'timeout' => 20,
        ]);

        $pdfStatus = $pdfResponse->getStatusCode();
        $pdfContent = $pdfResponse->getContent(false);
        if ($pdfStatus >= 400) {
            $message = trim($pdfContent);
            if ($message === '') {
                $message = 'InvoiceAPI PDF download failed with status ' . $pdfStatus;
            }

            throw new \RuntimeException($message);
        }

        return $pdfContent;
    }
}
