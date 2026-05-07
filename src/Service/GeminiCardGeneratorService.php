<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiCardGeneratorService
{
    private HttpClientInterface $httpClient;
    private ?string $geminiApiKey;

    public function __construct(HttpClientInterface $httpClient, ?string $geminiApiKey = null)
    {
        $this->httpClient = $httpClient;
        $this->geminiApiKey = $geminiApiKey;
    }

    /**
     * Génère une image de carte bancaire avec thème superposé.
     * Avec l’extension GD : fusion PNG côté serveur. Sans GD : URL du thème pour une superposition dans le navigateur.
     */
    public function generateCustomCard(string $imagePath, string $theme): GeneratedCardResult
    {
        $originalImage = file_get_contents($imagePath);
        if (!$originalImage) {
            throw new \Exception('Impossible de lire l\'image originale');
        }

        $themeImageUrl = $this->generateThemeOverlay($theme);
        $themeImage = $this->downloadImageWithTimeout($themeImageUrl, 90);

        if (!$themeImage) {
            throw new \Exception(
                'Impossible de télécharger l\'image de thème (service externe lent ou indisponible). Réessayez dans quelques instants ou raccourcissez le texte du thème.'
            );
        }

        if (\function_exists('imagecreatefromstring')) {
            $composedImage = $this->composeImages($originalImage, $themeImage);

            return new GeneratedCardResult(
                'data:image/png;base64,' . base64_encode($composedImage),
                $themeImageUrl,
                false,
            );
        }

        return new GeneratedCardResult(null, $themeImageUrl, true);
    }

    /**
     * Télécharge une image avec timeout explicite, retry et gestion d'erreur détaillée.
     */
    private function downloadImageWithTimeout(string $url, int $timeoutSeconds = 60): ?string
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $attempt++;
            error_log("Tentative $attempt/$maxRetries de téléchargement: $url");

            $result = $this->downloadWithHttpClient($url, $timeoutSeconds);
            if ($result !== null && $this->looksLikeImageBinary($result)) {
                error_log('✓ Téléchargement réussi (HttpClient)');
                return $result;
            }

            if (\function_exists('curl_init')) {
                $result = $this->downloadWithCurl($url, $timeoutSeconds);
                if ($result !== null && $this->looksLikeImageBinary($result)) {
                    error_log('✓ Téléchargement réussi (cURL)');
                    return $result;
                }
            }

            $result = $this->downloadWithStreamContext($url, $timeoutSeconds);
            if ($result !== null && $this->looksLikeImageBinary($result)) {
                error_log('✓ Téléchargement réussi (stream)');
                return $result;
            }

            if ($attempt < $maxRetries) {
                error_log("Tentative $attempt échouée, nouvelle tentative dans 3 secondes...");
                sleep(3);
            }
        }

        error_log("❌ Échec après $maxRetries tentatives de téléchargement: $url");
        return null;
    }

    private function downloadWithHttpClient(string $url, int $timeoutSeconds): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $timeoutSeconds,
                'max_duration' => $timeoutSeconds,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/png,image/jpeg,*/*;q=0.8',
                ],
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);
            if ($status !== 200 || $body === '') {
                error_log("HttpClient - HTTP $status, taille=" . \strlen($body));

                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            error_log('HttpClient: ' . $e->getMessage());

            return null;
        }
    }

    private function looksLikeImageBinary(string $data): bool
    {
        if (\strlen($data) < 32) {
            return false;
        }

        return str_starts_with($data, "\x89PNG\r\n\x1a\n")
            || str_starts_with($data, "\xff\xd8\xff")
            || str_starts_with($data, 'GIF8')
            || str_starts_with($data, 'RIFF');
    }

    /**
     * Télécharge avec stream_context et timeout.
     */
    private function downloadWithStreamContext(string $url, int $timeoutSeconds): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeoutSeconds,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'ignore_errors' => true // Récupérer même les réponses d'erreur
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        try {
            $imageData = @file_get_contents($url, false, $context);
            
            // Vérifier les headers HTTP
            if (!empty($http_response_header)) {
                $statusLine = $http_response_header[0];
                error_log("Stream Context - Response: $statusLine");
                
                if (strpos($statusLine, '200') === false) {
                    error_log("Stream Context - HTTP Error: $statusLine");
                    return null;
                }
            }
            
            if ($imageData !== false && $imageData !== '' && \strlen($imageData) >= 32) {
                return $imageData;
            }
            
            return null;
        } catch (\Exception $e) {
            error_log('Stream Context Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Télécharge avec cURL (plus performant et plus de contrôle).
     */
    private function downloadWithCurl(string $url, int $timeoutSeconds): ?string
    {
        $ch = curl_init();
        
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => min(30, $timeoutSeconds),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_VERBOSE => false
            ]);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            error_log("cURL - HTTP Code: $httpCode, Error: $curlError");
            
            if (!empty($curlError)) {
                error_log("cURL Error Details: $curlError");
                return null;
            }
            
            if ($httpCode !== 200) {
                error_log("cURL HTTP Error: Code $httpCode for $url");
                return null;
            }
            
            if ($imageData === false || $imageData === '' || \strlen($imageData) < 32) {
                error_log('cURL - Corps vide ou trop court (' . \strlen($imageData ?: '') . ' octets)');
                return null;
            }
            
            error_log("cURL - ✓ Données reçues: " . strlen($imageData) . " bytes");
            return $imageData;
        } catch (\Exception $e) {
            error_log('cURL Exception: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Génère une image de thème (motif, dégradé, décoration) à superposer sur la carte.
     */
    private function generateThemeOverlay(string $theme): string
    {
        $seed = random_int(1, 999999);
        $snippet = mb_substr(trim($theme), 0, 280);
        $themePrompt = "Abstract seamless background texture pattern. Theme: {$snippet}. No faces, no people, no text, no numbers. Flat decorative texture, full frame. High quality.";
        $encodedPrompt = rawurlencode($themePrompt);

        return "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=800&height=500&model=flux&nologo=true&seed={$seed}";
    }

    /**
     * Compose deux images ensemble : carte originale + thème par-dessus avec transparence.
     * Utilise PHP GD library pour la composition avec imagecopymerge.
     */
    private function composeImages(string $baseImageData, string $overlayImageData): string
    {
        try {
            $baseImage = \imagecreatefromstring($baseImageData);
            $overlayImage = \imagecreatefromstring($overlayImageData);
            
            if (!$baseImage || !$overlayImage) {
                throw new \Exception('Impossible de créer les ressources images');
            }
            
            $baseWidth = \imagesx($baseImage);
            $baseHeight = \imagesy($baseImage);
            $overlayWidth = \imagesx($overlayImage);
            $overlayHeight = \imagesy($overlayImage);
            
            if ($overlayWidth !== $baseWidth || $overlayHeight !== $baseHeight) {
                $resizedOverlay = \imagecreatetruecolor($baseWidth, $baseHeight);
                \imagecopyresampled(
                    $resizedOverlay,
                    $overlayImage,
                    0, 0,
                    0, 0,
                    $baseWidth, $baseHeight,
                    $overlayWidth, $overlayHeight
                );
                \imagedestroy($overlayImage);
                $overlayImage = $resizedOverlay;
            }
            
            \imagecopymerge(
                $baseImage,
                $overlayImage,
                0, 0,
                0, 0,
                $baseWidth,
                $baseHeight,
                35
            );
            
            \ob_start();
            \imagepng($baseImage, null, 9);
            $imageData = \ob_get_clean();
            
            \imagedestroy($baseImage);
            \imagedestroy($overlayImage);
            
            return $imageData;
        } catch (\Exception $e) {
            if (isset($baseImage)) {
                \imagedestroy($baseImage);
            }
            if (isset($overlayImage)) {
                \imagedestroy($overlayImage);
            }
            throw $e;
        }
    }
}