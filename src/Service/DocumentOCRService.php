<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service d'extraction de texte des documents PDF et images
 */
class DocumentOCRService
{
    private string $uploadsDir;
    private HttpClientInterface $httpClient;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        \Psr\Log\LoggerInterface $logger,
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

            // Essayer avec pdftotext
            $text = $this->extractWithPdfToText($filePath);
            if ($text !== null) {
                $this->logger->info('OCR: Success with pdftotext');
                return $text;
            }

            // Essayer avec la librairie PHP PDF Parser
            $text = $this->extractWithPhpPdfParser($filePath);
            if ($text !== null) {
                $this->logger->info('OCR: Success with PHP PDF Parser');
                return $text;
            }

            // Fallback : utiliser une API externe gratuite
            $this->logger->info('OCR: Falling back to external APIs');
            return $this->extractWithExternalAPI($filePath);
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
            $checkCommand = 'which pdftotext 2>/dev/null';
            $result = shell_exec($checkCommand);
            
            if (empty($result)) {
                return null;
            }

            $tempFile = sys_get_temp_dir() . '/' . uniqid('pdf_text_') . '.txt';
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
     * Extrait le texte avec la librairie PHP PDF Parser
     */
    private function extractWithPhpPdfParser(string $filePath): ?string
    {
        try {
            // Vérifier si la classe existe (après composer install)
            if (!class_exists('Smalot\PdfParser\Parser')) {
                return null;
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            return !empty($text) ? $text : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extrait avec une API externe gratuite (PDFShift ou similaire)
     */
    private function extractWithExternalAPI(string $filePath): ?string
    {
        try {
            if (!file_exists($filePath)) {
                return null;
            }

            // Méthode 1 : Essayer avec CloudConvert API (gratuit)
            $text = $this->extractWithCloudConvert($filePath);
            if ($text !== null && !empty($text)) {
                return $text;
            }

            // Méthode 2 : Essayer avec PDF2Go (gratuit)
            $text = $this->extractWithPdf2Go($filePath);
            if ($text !== null && !empty($text)) {
                return $text;
            }

            // Méthode 3 : Essayer avec OCR.space (très fiable pour les PDFs de moins de 1Mo)
            $text = $this->extractWithOCRSpace($filePath);
            if ($text !== null && !empty($text)) {
                return $text;
            }

            // Méthode 4 : Essayer avec une approche alternative (extraction brute)
            $text = $this->extractRawPDFText($filePath);
            if ($text !== null && !empty($text)) {
                return $text;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extrait avec CloudConvert API (gratuit - 25 conversions/jour)
     */
    private function extractWithCloudConvert(string $filePath): ?string
    {
        try {
            $fileContent = file_get_contents($filePath);
            
            // Créer un formulaire multipart
            $boundary = '----WebKitFormBoundary' . bin2hex(random_bytes(16));
            $body = '';
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($filePath) . '"' . "\r\n";
            $body .= 'Content-Type: application/pdf' . "\r\n\r\n";
            $body .= $fileContent . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Disposition: form-data; name="outputformat"' . "\r\n\r\n";
            $body .= 'txt' . "\r\n";
            $body .= "--{$boundary}--\r\n";

            $response = $this->httpClient->request('POST', 'https://api.cloudconvert.com/v2/convert', [
                'headers' => [
                    'Content-Type' => "multipart/form-data; boundary={$boundary}",
                ],
                'body' => $body,
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
                $data = $response->toArray();
                if (isset($data['data']['output'][0]) && isset($data['data']['output'][0]['url'])) {
                    $outputUrl = $data['data']['output'][0]['url'];
                    $textContent = file_get_contents($outputUrl);
                    return $textContent ?: null;
                }
            }
        } catch (\Exception $e) {
            // Fallback silencieux
        }

        return null;
    }

    /**
     * Extrait avec PDF2Go API (gratuit)
     */
    private function extractWithPdf2Go(string $filePath): ?string
    {
        try {
            $fileContent = file_get_contents($filePath);
            $base64 = base64_encode($fileContent);

            $response = $this->httpClient->request('POST', 'https://api.pdf2go.com/v1/document/convertTo/txt', [
                'json' => [
                    'file' => $base64,
                    'filename' => basename($filePath),
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                if (isset($data['Files'][0]['FileContent'])) {
                    $decoded = base64_decode($data['Files'][0]['FileContent']);
                    return $decoded ?: null;
                }
            }
        } catch (\Exception $e) {
            //
        }

        return null;
    }

    /**
     * Extraction brute du texte d'un PDF (approche simple sans dépendances externes)
     */
    private function extractRawPDFText(string $filePath): ?string
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }

            // Chercher les flux de texte dans le PDF
            $text = '';

            // Pattern pour extraire le texte des objets PDF
            $patterns = [
                '/BT\s+(.*?)\s+ET/s',  // Texte entre BT et ET
                '/\(([^)]+)\)/s',       // Texte entre parenthèses
            ];

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        $decoded = $this->decodePDFString($match);
                        if (!empty($decoded)) {
                            $text .= $decoded . ' ';
                        }
                    }
                }
            }

            // Décompresser le contenu si compressed
            if (strpos($content, '/FlateDecode') !== false) {
                $text .= $this->extractCompressedPDFText($content);
            }

            $text = trim(preg_replace('/\s+/', ' ', $text));
            return !empty($text) ? $text : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Décode une chaîne PDF
     */
    private function decodePDFString(string $str): string
    {
        // Supprimer les contrôles PDF
        $str = preg_replace(['/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\0/'], ["\n", "\r", "\t", "\0"], $str);
        
        // Supprimer les codes de contrôle
        $str = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $str);
        
        return trim($str);
    }

    /**
     * Extrait le texte des PDFs compressés
     */
    private function extractCompressedPDFText(string $content): string
    {
        $text = '';
        
        try {
            // Chercher les flux compressés
            preg_match_all('/stream\s+(.*?)\s+endstream/s', $content, $matches);
            
            foreach ($matches[1] as $stream) {
                try {
                    $decompressed = @gzuncompress($stream);
                    if ($decompressed !== false) {
                        $text .= $this->extractRawTextFromStream($decompressed) . ' ';
                    }
                } catch (\Exception $e) {
                    // Continuer au prochain stream
                }
            }
        } catch (\Exception $e) {
            //
        }

        return $text;
    }

    /**
     * Extrait le texte d'un flux PDF
     */
    private function extractRawTextFromStream(string $stream): string
    {
        $text = '';
        
        // Chercher les chaînes entre guillemets
        if (preg_match_all('/\(([^)]*)\)/', $stream, $matches)) {
            foreach ($matches[1] as $str) {
                $decoded = $this->decodePDFString($str);
                if (!empty($decoded)) {
                    $text .= $decoded . ' ';
                }
            }
        }

        return trim($text);
    }

    /**
     * Extrait le texte d'une image via OCR (Tesseract)
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
            return $this->extractImageWithExternalAPI($filePath);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extrait avec Tesseract (outil système)
     */
    private function extractWithTesseract(string $filePath): ?string
    {
        try {
            $checkCommand = 'which tesseract 2>/dev/null';
            $result = shell_exec($checkCommand);
            
            if (empty($result)) {
                return null;
            }

            $tempFile = sys_get_temp_dir() . '/' . uniqid('ocr_text_');
            $command = sprintf('tesseract %s %s 2>&1', escapeshellarg($filePath), escapeshellarg($tempFile));
            
            @shell_exec($command);
            
            if (file_exists($tempFile . '.txt')) {
                $text = file_get_contents($tempFile . '.txt');
                @unlink($tempFile . '.txt');
                return !empty($text) ? $text : null;
            }
        } catch (\Exception $e) {
            //
        }

        return null;
    }

    /**
     * Extrait le texte d'une image via API externe
     */
    private function extractImageWithExternalAPI(string $filePath): ?string
    {
        try {
            if (!file_exists($filePath)) {
                return null;
            }

            // Méthode 1 : OCR.space API (gratuit)
            $text = $this->extractWithOCRSpace($filePath);
            if ($text !== null && !empty($text)) {
                return $text;
            }

            // Méthode 2 : Google Vision API (gratuit 1000 requests/mois)
            $text = $this->extractWithGoogleVision($filePath);
            if ($text !== null && !empty($text)) {
                return $text;
            }

            // Méthode 3 : Azure Computer Vision API (gratuit 5000 requests/mois)
            $text = $this->extractWithAzureVision($filePath);
            if ($text !== null && !empty($text)) {
                return $text;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * OCR avec OCR.space API (Gratuit)
     */
    private function extractWithOCRSpace(string $filePath): ?string
    {
        try {
            $base64 = 'data:image/' . pathinfo($filePath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($filePath));

            $response = $this->httpClient->request('POST', 'https://api.ocr.space/parse', [
                'body' => [
                    'apikey' => 'K87899142591',
                    'base64image' => $base64,
                    'language' => 'fre',
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                if (isset($data['ParsedResults'][0]['ParsedText'])) {
                    return $data['ParsedResults'][0]['ParsedText'];
                }
            }
        } catch (\Exception $e) {
            //
        }

        return null;
    }

    /**
     * OCR avec Google Vision API (Gratuit 1000 req/mois)
     */
    private function extractWithGoogleVision(string $filePath): ?string
    {
        try {
            $fileContent = base64_encode(file_get_contents($filePath));
            
            // Utiliser une clé API de test (limité mais gratuit)
            $apiKey = 'AIzaSyA-jJNqR8tTwIz9_wc6UpC_FczP7qz4lIg';  // Clé de démonstration

            $response = $this->httpClient->request('POST', 
                'https://vision.googleapis.com/v1/images:annotate?key=' . $apiKey,
                [
                    'json' => [
                        'requests' => [
                            [
                                'image' => [
                                    'content' => $fileContent,
                                ],
                                'features' => [
                                    [
                                        'type' => 'TEXT_DETECTION',
                                        'maxResults' => 10,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'timeout' => 30,
                ]
            );

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                if (isset($data['responses'][0]['textAnnotations'])) {
                    $text = $data['responses'][0]['textAnnotations'][0]['description'] ?? '';
                    return !empty($text) ? $text : null;
                }
            }
        } catch (\Exception $e) {
            //
        }

        return null;
    }

    /**
     * OCR avec Azure Computer Vision API (Gratuit 5000 req/mois)
     */
    private function extractWithAzureVision(string $filePath): ?string
    {
        try {
            $fileContent = file_get_contents($filePath);

            $response = $this->httpClient->request('POST',
                'https://westeurope.api.cognitive.microsoft.com/vision/v3.2/ocr',
                [
                    'headers' => [
                        'Ocp-Apim-Subscription-Key' => 'demo-key',
                        'Content-Type' => 'application/octet-stream',
                    ],
                    'body' => $fileContent,
                    'query' => [
                        'language' => 'unk',
                    ],
                    'timeout' => 30,
                ]
            );

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                $text = '';
                
                if (isset($data['regions'])) {
                    foreach ($data['regions'] as $region) {
                        foreach ($region['lines'] as $line) {
                            foreach ($line['words'] as $word) {
                                $text .= $word['text'] . ' ';
                            }
                            $text .= "\n";
                        }
                    }
                }

                return !empty($text) ? $text : null;
            }
        } catch (\Exception $e) {
            //
        }

        return null;
    }

    /**
     * Limite le texte à un nombre maximum de caractères
     */
    public function limitText(string $text, int $maxChars = 5000): string
    {
        if (strlen($text) > $maxChars) {
            return substr($text, 0, $maxChars) . "\n\n...[Texte tronqué pour la traduction]";
        }
        return $text;
    }

    /**
     * Nettoie le texte extrait
     */
    public function cleanText(string $text): string
    {
        // Supprimer les caractères de contrôle (sauf \n et \r)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normaliser les espaces horizontaux sans toucher aux sauts de ligne
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Supprimer les espaces vides en début/fin de ligne
        $text = preg_replace('/^[ \t]+|[ \t]+$/m', '', $text);
        
        // Limiter à max 2 sauts de ligne consécutifs
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
