<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service d'extraction de texte des documents PDF et images
 */
class DocumentOCRService
{
    private string $uploadsDir;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $uploadsDir = '%kernel.project_dir%/public/uploads'
    ) {
        $this->uploadsDir = $uploadsDir;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Extrait le texte d'un fichier PDF
     */
    public function extractTextFromPDF(string $filePath): ?string
    {
        $this->logger->info('OCR: Starting PDF extraction for ' . $filePath);
        try {
            if (!file_exists($filePath)) {
                $this->logger->error('OCR: File not found: ' . $filePath);
                return null;
            }

            // Ordre de priorité des méthodes d'extraction (plus fiable en premier)
            
            // 1. Essayer avec la librairie PHP PDF Parser (meilleure pour les PDFs texte)
            $text = $this->extractWithPhpPdfParser($filePath);
            if ($text !== null && strlen($text) > 10) {
                $this->logger->info('OCR: Success with PHP PDF Parser');
                return $text;
            }

            // 2. Essayer avec pdftotext (si disponible sur le système)
            $text = $this->extractWithPdfToText($filePath);
            if ($text !== null && strlen($text) > 10) {
                $this->logger->info('OCR: Success with pdftotext');
                return $text;
            }

            // 3. Utiliser une API externe pour les PDFs scannés ou complexes
            $this->logger->info('OCR: Falling back to external API (OCR.space)');
            $text = $this->extractWithOCRSpace($filePath);
            if ($text !== null && strlen($text) > 5) {
                return $text;
            }

            // 4. Dernière tentative : extraction brute (très peu fiable)
            $this->logger->warning('OCR: Using fallback raw extraction (least reliable)');
            return $this->extractRawPDFText($filePath);
        } catch (\Exception $e) {
            $this->logger->error('OCR: Error in extractTextFromPDF: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrait avec pdftotext (outil système)
     */
    private function extractWithPdfToText(string $filePath): ?string
    {
        try {
            // Vérifier que pdftotext est disponible
            $checkCommand = 'where pdftotext 2>nul'; // Windows specific check
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $checkCommand = 'which pdftotext 2>/dev/null';
            }
            $result = shell_exec($checkCommand);
            
            if (empty($result)) {
                return null;
            }

            $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pdf_text_') . '.txt';
            $command = sprintf('pdftotext %s %s 2>&1', escapeshellarg($filePath), escapeshellarg($tempFile));
            
            @shell_exec($command);
            
            if (file_exists($tempFile)) {
                $text = file_get_contents($tempFile);
                @unlink($tempFile);
                return !empty($text) ? $text : null;
            }
        } catch (\Exception $e) {
            //
        }

        return null;
    }

    /**
     * Extrait le texte avec la librairie PHP PDF Parser (Smalot)
     */
    private function extractWithPhpPdfParser(string $filePath): ?string
    {
        try {
            if (!class_exists('Smalot\PdfParser\Parser')) {
                $this->logger->debug('PDF Parser library not available');
                return null;
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            
            // Extraire le texte principal
            $text = $pdf->getText();
            
            // Nettoyer le texte
            if (!empty($text)) {
                // Supprimer les caractères de contrôle et les encodages bizarres
                $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
                $text = trim($text);
                
                // Vérifier que ce n'est pas du charabia
                if (strlen($text) > 5 && preg_match('/[a-zA-Z0-9]/u', $text)) {
                    return $text;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->debug('PHP PDF Parser error: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Extraction brute du texte d'un PDF (approche simple sans dépendances externes)
     * ATTENTION: Cette méthode est très peu fiable pour les PDFs générés par dompdf
     */
    private function extractRawPDFText(string $filePath): ?string
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }

            $text = '';

            // Étape 1: Essayer d'extraire le texte brut non compressé
            $patterns = [
                '/BT\s+(.*?)\s+ET/s',  // Text between BT and ET operators
                '/\(([^)]*?)\)\s*T[j|w|m|d|*]/s',  // Text with text position operators
            ];

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        $decoded = $this->decodePDFString($match);
                        if (!empty($decoded) && strlen($decoded) > 2) {
                            $text .= $decoded . ' ';
                        }
                    }
                }
            }

            // Étape 2: Essayer d'extraire du texte compressé (FlateDecode)
            if (strpos($content, 'stream') !== false) {
                $text .= $this->extractCompressedPDFText($content);
            }

            // Nettoyer le texte
            $text = trim(preg_replace('/\s+/', ' ', $text));
            
            // Vérifier que ce n'est pas du charabia
            if (strlen($text) > 10 && preg_match('/[a-zA-Z0-9]/u', $text)) {
                return $text;
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->debug('Raw PDF extraction error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Décode et nettoie une chaîne PDF
     */
    private function decodePDFString(string $str): string
    {
        // Convertir les séquences d'échappement PDF
        $str = preg_replace([
            '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\\\\\/', '/\\\\0/'
        ], [
            "\n", "\r", "\t", "\\", "\0"
        ], $str);
        
        // Remplacer les codes hexadécimaux PDF (\xHH)
        $str = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function($m) {
            return chr(hexdec($m[1]));
        }, $str);
        
        // Supprimer les caractères de contrôle et non imprimables
        // Garder seulement: ASCII 32-126 (texte normal) + 160-255 (caractères étendus) + newlines/tabs
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
        
        // Supprimer les caractères binaires suspects (souvent du charabia du PDF)
        // Garder les caractères Unicode valides
        $str = preg_replace('/[\x80-\x9F]/u', '', $str);
        
        // Convertir les entités HTML si présentes
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return trim($str);
    }

    /**
     * Extrait le texte depuis les streams compressés (FlateDecode)
     */
    private function extractCompressedPDFText(string $content): string
    {
        $text = '';
        try {
            // Chercher les streams FlateDecode
            preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $matches);
            
            foreach ($matches[1] as $stream) {
                // Le contenu peut être compressé avec gzip/flate
                $decompressed = null;
                
                // Essai 1: gzuncompress (zlib)
                try {
                    @$decompressed = gzuncompress($stream);
                } catch (\Exception $e) {}
                
                // Essai 2: gzinflate (zlib avec header)
                if ($decompressed === false) {
                    try {
                        @$decompressed = gzinflate($stream);
                    } catch (\Exception $e) {}
                }
                
                if ($decompressed !== false && !empty($decompressed)) {
                    $extracted = $this->extractRawTextFromStream($decompressed);
                    if (!empty($extracted)) {
                        $text .= $extracted . ' ';
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('Compressed PDF extraction error: ' . $e->getMessage());
        }
        
        return trim($text);
    }

    /**
     * Extrait le texte brut depuis un stream décompressé
     */
    private function extractRawTextFromStream(string $stream): string
    {
        $text = '';
        
        // Pattern 1: Texte entre parenthèses (format PDF standard)
        if (preg_match_all('/\(([^()]*?)\)/', $stream, $matches)) {
            foreach ($matches[1] as $str) {
                $decoded = $this->decodePDFString($str);
                if (!empty($decoded) && strlen($decoded) > 1) {
                    $text .= $decoded . ' ';
                }
            }
        }
        
        // Pattern 2: Texte entre < et > (format hexadécimal)
        if (preg_match_all('/<([0-9a-fA-F]+)>/', $stream, $matches)) {
            foreach ($matches[1] as $hexStr) {
                try {
                    $decoded = hex2bin($hexStr);
                    if ($decoded !== false) {
                        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $decoded);
                        $cleaned = trim($cleaned);
                        if (!empty($cleaned) && strlen($cleaned) > 1) {
                            $text .= $cleaned . ' ';
                        }
                    }
                } catch (\Exception $e) {}
            }
        }
        
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Extrait le texte d'une image via OCR
     */
    public function extractTextFromImage(string $filePath): ?string
    {
        try {
            if (!file_exists($filePath)) {
                return null;
            }

            // Essayer avec Tesseract si disponible
            $text = $this->extractWithTesseract($filePath);
            if ($text !== null) {
                return $text;
            }

            // Fallback : utiliser une API externe
            return $this->extractWithOCRSpace($filePath);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractWithTesseract(string $filePath): ?string
    {
        try {
            $checkCommand = 'where tesseract 2>nul';
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $checkCommand = 'which tesseract 2>/dev/null';
            }
            $result = shell_exec($checkCommand);
            
            if (empty($result)) {
                return null;
            }

            $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('ocr_text_');
            $command = sprintf('tesseract %s %s 2>&1', escapeshellarg($filePath), escapeshellarg($tempFile));
            
            @shell_exec($command);
            
            if (file_exists($tempFile . '.txt')) {
                $text = file_get_contents($tempFile . '.txt');
                @unlink($tempFile . '.txt');
                return !empty($text) ? $text : null;
            }
        } catch (\Exception $e) {}
        return null;
    }

    /**
     * Extrait le texte avec OCR.Space API (gratuit)
     */
    private function extractWithOCRSpace(string $filePath): ?string
    {
        try {
            $fileSize = filesize($filePath);
            
            // OCR.Space a une limite de 1MB pour les fichiers
            if ($fileSize > 1024 * 1024) {
                $this->logger->warning("PDF file too large for OCR.Space: " . ($fileSize / 1024 / 1024) . "MB");
                return null;
            }

            if (!file_exists($filePath)) {
                return null;
            }

            $fileContent = file_get_contents($filePath);
            $base64 = 'data:application/pdf;base64,' . base64_encode($fileContent);

            $response = $this->httpClient->request('POST', 'https://api.ocr.space/parse', [
                'body' => http_build_query([
                    'apikey' => 'K87899142591', // Free API key
                    'base64image' => $base64,
                    'language' => 'fre,eng', // French and English
                    'isOverlayRequired' => 'false',
                ]),
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'timeout' => 45,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray(false);
                
                if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing'] === false) {
                    if (isset($data['ParsedResults'][0]['ParsedText'])) {
                        $text = trim($data['ParsedResults'][0]['ParsedText']);
                        if (!empty($text) && strlen($text) > 5) {
                            $this->logger->info("OCR.Space extraction successful");
                            return $text;
                        }
                    }
                } else {
                    $this->logger->warning("OCR.Space error: " . ($data['ErrorMessage'][0] ?? 'Unknown error'));
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('OCR.Space API error: ' . $e->getMessage());
        }

        return null;
    }

    public function limitText(string $text, int $maxChars = 5000): string
    {
        if (strlen($text) > $maxChars) {
            return substr($text, 0, $maxChars) . "\n\n...[Texte tronqué]";
        }
        return $text;
    }

    public function cleanText(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/^[ \t]+|[ \t]+$/m', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
