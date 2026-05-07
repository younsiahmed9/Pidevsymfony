<?php

namespace App\Service\Admin;

use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Snappy\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class PdfExportService
{
    public function __construct(private ?Pdf $snappyPdf = null)
    {
    }

    public function renderPdfResponse(string $html, string $filename, string $paper = 'A4', string $orientation = 'portrait'): Response
    {
        $content = $this->renderContent($html, $paper, $orientation);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
        );

        return $response;
    }

    public function renderPdfContent(string $html, string $filename, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        return $this->renderContent($html, $paper, $orientation);
    }

    private function renderContent(string $html, string $paper, string $orientation): string
    {
        if ($this->snappyPdf instanceof Pdf) {
            try {
                return $this->snappyPdf->getOutputFromHtml($html, [
                    'encoding' => 'UTF-8',
                    'page-size' => $paper,
                    'orientation' => ucfirst(strtolower($orientation)),
                    'enable-local-file-access' => true,
                    'print-media-type' => true,
                ]);
            } catch (\Throwable) {
                // Fallback to Dompdf when wkhtmltopdf is unavailable.
            }
        }

        $options = new Options();
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }
}
