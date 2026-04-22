<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de traduction utilisant Google Translate API ou un service libre
 */
class TranslationService
{
    private HttpClientInterface $client;
    private string $translationApiKey;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $client,
        LoggerInterface $logger,
        string $googleTranslateApiKey = ''
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->translationApiKey = $googleTranslateApiKey;
    }

    /**
     * Traduit un texte d'une langue source à une langue cible
     */
    public function translateText(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        if (empty(trim($text))) {
            return $text;
        }

        try {
            // Découper le texte en morceaux si trop long (max 1000 caractères par morceau pour les APIs gratuites)
            $chunks = $this->splitTextIntoChunks($text, 1000);
            $translatedChunks = [];

            foreach ($chunks as $chunk) {
                $translatedChunk = null;
                
                if (empty($this->translationApiKey)) {
                    $translatedChunk = $this->translateWithFreeService($chunk, $targetLanguage, $sourceLanguage);
                    
                    if (!$translatedChunk) {
                        $this->logger->info('MyMemory failed, trying Lingva fallback...');
                        $translatedChunk = $this->translateWithLingva($chunk, $targetLanguage, $sourceLanguage);
                    }
                } else {
                    $translatedChunk = $this->translateWithGoogleAPI($chunk, $targetLanguage, $sourceLanguage);
                }

                if ($translatedChunk === null) {
                    throw new \Exception('Impossible de traduire un morceau du document.');
                }
                
                $translatedChunks[] = $translatedChunk;
            }

            return implode(' ', $translatedChunks);
        } catch (\Exception $e) {
            $this->logger->error('Translation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Service de traduction gratuit (MyMemory)
     */
    private function translateWithFreeService(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        try {
            // MyMemory free tier works better with GET for small chunks
            // The text is already chunked to 1000 chars
            $response = $this->client->request('GET', 'https://api.mymemory.translated.net/get', [
                'query' => [
                    'q' => $text,
                    'langpair' => strtolower($sourceLanguage) . '|' . strtolower($targetLanguage),
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
            
            if (isset($data['responseStatus']) && $data['responseStatus'] == 200 && isset($data['responseData']['translatedText'])) {
                return html_entity_decode($data['responseData']['translatedText']);
            }
            
            if (isset($data['responseStatus'])) {
                $this->logger->warning('MyMemory returned status ' . $data['responseStatus'] . ': ' . ($data['responseDetails'] ?? 'No details') . ' | Langpair: ' . $sourceLanguage . '|' . $targetLanguage);
            }
        } catch (\Exception $e) {
            $this->logger->error('MyMemory API Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Divise un texte en morceaux en respectant les fins de phrases
     */
    private function splitTextIntoChunks(string $text, int $maxLength): array
    {
        if (strlen($text) <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            if (strlen($currentChunk . ' ' . $sentence) > $maxLength) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                
                // Si une seule phrase est plus longue que maxLength, on la coupe brutalement
                if (strlen($sentence) > $maxLength) {
                    $parts = str_split($sentence, $maxLength);
                    $lastPart = array_pop($parts);
                    foreach ($parts as $part) {
                        $chunks[] = $part;
                    }
                    $currentChunk = $lastPart;
                } else {
                    $currentChunk = $sentence;
                }
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : ' ') . $sentence;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Fallback: Lingva Translate (Instance publique Libre)
     */
    private function translateWithLingva(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        // Liste d'instances Lingva pour plus de robustesse
        $instances = [
            'https://lingva.garudalinux.org',
            'https://translate.plausibility.cloud',
            'https://lingva.lunar.icu',
            'https://lingva.ml'
        ];

        $source = strtolower($sourceLanguage) === 'auto' ? 'auto' : strtolower($sourceLanguage);
        $target = strtolower($targetLanguage);

        foreach ($instances as $baseUrl) {
            try {
                // Tentative via POST (plus moderne)
                $response = $this->client->request('POST', $baseUrl . '/api/v1/translate', [
                    'json' => [
                        'source' => $source,
                        'target' => $target,
                        'query' => $text
                    ],
                    'timeout' => 8,
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray(false);
                    if (isset($data['translation'])) {
                        return $data['translation'];
                    }
                }

                // Tentative via GET (legacy fallback)
                $url = sprintf('%s/api/v1/%s/%s/%s', $baseUrl, $source, $target, urlencode($text));
                $response = $this->client->request('GET', $url, ['timeout' => 8]);
                
                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray(false);
                    if (isset($data['translation'])) {
                        return $data['translation'];
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning("Lingva instance $baseUrl failed: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /**
     * Google Translate API (payant)
     */
    private function translateWithGoogleAPI(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        try {
            $response = $this->client->request('POST', 'https://translation.googleapis.com/language/translate/v2', [
                'query' => ['key' => $this->translationApiKey],
                'json' => [
                    'q' => $text,
                    'target' => $targetLanguage,
                    'source' => $sourceLanguage !== 'auto' ? $sourceLanguage : '',
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray();
            
            if (isset($data['data']['translations'][0]['translatedText'])) {
                return html_entity_decode($data['data']['translations'][0]['translatedText']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Google Translate API Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Détecte la langue d'un texte
     */
    public function detectLanguage(string $text): ?string
    {
        try {
            $response = $this->client->request('GET', 'https://api.mymemory.translated.net/get', [
                'query' => [
                    'q' => substr($text, 0, 100),
                    'langpair' => 'en|fr',
                ],
                'timeout' => 3,
            ]);

            $data = $response->toArray(false);
            
            if (isset($data['responseData']['match'])) {
                return $data['responseData']['match'] > 0.8 ? 'fr' : 'en';
            }
        } catch (\Exception $e) {
            $this->logger->error('Language detection Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Obtient la liste des langues supportées
     */
    public function getSupportedLanguages(): array
    {
        return [
            'en' => 'English',
            'fr' => 'Français',
            'es' => 'Español',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ru' => 'Русский',
            'ja' => '日本語',
            'zh' => '中文',
            'ar' => 'العربية',
            'ko' => '한국어',
            'tr' => 'Türkçe',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'vi' => 'Tiếng Việt',
        ];
    }
}
