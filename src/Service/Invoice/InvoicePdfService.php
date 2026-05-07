<?php

namespace App\Service\Invoice;

use App\Entity\User;
use App\Service\Admin\PdfExportService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class InvoicePdfService
{
    public function __construct(
        private Environment $twig,
        private PdfExportService $pdfExportService,
        private InvoiceQrCodeService $invoiceQrCodeService,
        private UrlGeneratorInterface $urlGenerator,
        private string $symfonyBaseUrl,
        private string $appSecret,
    ) {
    }

    /**
     * @param array<string, mixed> $product
     */
    public function generateProductInvoiceResponse(User $user, array $product, ?string $publicBaseUrl = null): Response
    {
        $productId = (int) ($product['id'] ?? 0);
        $amount = (float) ($product['montant'] ?? 0);

        if ($productId <= 0 || $amount <= 0) {
            throw new \RuntimeException('Invalid product data for invoice generation.');
        }

        $userId = $user->getId();
        if ($userId === null || $userId <= 0) {
            throw new \RuntimeException('Invalid user data for invoice generation.');
        }

        $generatedAt = new \DateTimeImmutable();
        $invoiceNumber = sprintf('INV-PRD-%d-%s', $productId, $generatedAt->format('YmdHis'));
        $pdfUrl = $this->buildPublicInvoiceUrl($productId, $userId, $publicBaseUrl);

        $html = $this->twig->render('frontoffice/invoice/product_invoice_pdf.html.twig', [
            'user' => $user,
            'product' => $product,
            'invoiceNumber' => $invoiceNumber,
            'generatedAt' => $generatedAt,
            'verificationUrl' => $pdfUrl,
            'qrCodeDataUri' => $this->invoiceQrCodeService->generateQrCodeDataUri($pdfUrl),
        ]);

        return $this->pdfExportService->renderPdfResponse(
            $html,
            sprintf('facture-produit-%d-%s.pdf', $productId, $generatedAt->format('Ymd-His'))
        );
    }

    /**
     * @param array<string, mixed> $product
     */
    public function generateProductInvoiceQrSvg(User $user, array $product, ?string $publicBaseUrl = null): string
    {
        $productId = (int) ($product['id'] ?? 0);
        $amount = (float) ($product['montant'] ?? 0);

        if ($productId <= 0 || $amount <= 0) {
            throw new \RuntimeException('Invalid product data for QR generation.');
        }

        $userId = $user->getId();
        if ($userId === null || $userId <= 0) {
            throw new \RuntimeException('Invalid user data for QR generation.');
        }

        return $this->invoiceQrCodeService->generateQrCodeSvg($this->buildPublicInvoiceUrl($productId, $userId, $publicBaseUrl));
    }

    /**
     * @return array{productId:int,userId:int,expiresAt:int}|null
     */
    public function parsePublicInvoiceToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($parts[0]);
        $signature = $this->base64UrlDecode($parts[1]);

        if ($payloadJson === null || $signature === null) {
            return null;
        }

        $expected = hash_hmac('sha256', $payloadJson, $this->appSecret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $productId = (int) ($payload['product_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $expiresAt = (int) ($payload['exp'] ?? 0);

        if ($productId <= 0 || $userId <= 0 || $expiresAt <= time()) {
            return null;
        }

        return [
            'productId' => $productId,
            'userId' => $userId,
            'expiresAt' => $expiresAt,
        ];
    }

    private function buildPublicInvoiceUrl(int $productId, int $userId, ?string $publicBaseUrl): string
    {
        $token = $this->generatePublicInvoiceToken($productId, $userId);

        $routePath = $this->urlGenerator->generate('api_invoice_public_pdf', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        if ($publicBaseUrl === null) {
            $envBase = trim($this->symfonyBaseUrl);

            return $envBase !== '' ? rtrim($envBase, '/') . $routePath : $routePath;
        }

        $envBase = trim($this->symfonyBaseUrl);
        $requestBase = trim((string) $publicBaseUrl);

        if ($requestBase === '') {
            return $envBase !== '' ? rtrim($envBase, '/') . $routePath : $routePath;
        }

        if ($envBase === '') {
            return rtrim($requestBase, '/') . $routePath;
        }

        $host = (string) parse_url($requestBase, PHP_URL_HOST);
        if ($host === 'localhost' || $host === '127.0.0.1') {
            return rtrim($envBase, '/') . $routePath;
        }

        return rtrim($requestBase, '/') . $routePath;
    }

    private function generatePublicInvoiceToken(int $productId, int $userId): string
    {
        $payload = [
            'product_id' => $productId,
            'user_id' => $userId,
            'exp' => (new \DateTimeImmutable('+30 days'))->getTimestamp(),
        ];

        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payloadJson, $this->appSecret, true);

        return $this->base64UrlEncode($payloadJson) . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
