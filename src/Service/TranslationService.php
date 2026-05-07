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
            // Pour les textes courts (< 500 chars), ne pas découper
            if (strlen($text) < 500) {
                return $this->translateChunk($text, $targetLanguage, $sourceLanguage);
            }

            // Découper le texte en morceaux si trop long (max 800 caractères)
            $chunks = $this->splitTextIntoChunks($text, 800);
            $translatedChunks = [];

            foreach ($chunks as $chunk) {
                $translatedChunk = $this->translateChunk($chunk, $targetLanguage, $sourceLanguage);

                if ($translatedChunk === null) {
                    $this->logger->warning('Failed to translate chunk, using original');
                    $translatedChunk = $chunk; // Fallback to original if translation fails
                }
                
                $translatedChunks[] = $translatedChunk;
            }

            // Rejoindre les chunks avec respect de la ponctuation
            return $this->joinTranslatedChunks($translatedChunks);
        } catch (\Exception $e) {
            $this->logger->error('Translation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Traduit un chunk unique de texte avec retry logic
     */
    private function translateChunk(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        $translatedChunk = null;
        $normalizedTarget = $this->normalizeLangCode($targetLanguage);
        
        // Langues complexes qui nécessitent des APIs plus robustes
        $complexLanguages = ['ar', 'zh', 'ja', 'ko', 'he', 'th'];
        $isComplexTarget = in_array($normalizedTarget, $complexLanguages);
        
        if (empty($this->translationApiKey)) {
            // Pour les langues complexes, essayer MyMemory en premier (plus fiable pour RTL)
            if ($isComplexTarget) {
                $this->logger->info("Using MyMemory first for complex language: $normalizedTarget");
                $translatedChunk = $this->translateWithFreeService($text, $normalizedTarget, $sourceLanguage);
                
                if (!$translatedChunk) {
                    $this->logger->info('MyMemory failed, trying Lingva...');
                    $translatedChunk = $this->translateWithLingva($text, $normalizedTarget, $sourceLanguage);
                }
            } else {
                // Pour les langues simples, Lingva en premier
                $translatedChunk = $this->translateWithLingva($text, $normalizedTarget, $sourceLanguage);
                
                if (!$translatedChunk) {
                    $this->logger->info('Lingva failed, trying MyMemory...');
                    $translatedChunk = $this->translateWithFreeService($text, $normalizedTarget, $sourceLanguage);
                }
            }
        } else {
            $translatedChunk = $this->translateWithGoogleAPI($text, $normalizedTarget, $sourceLanguage);
        }

        return $translatedChunk;
    }

    /**
     * Rejoindre les chunks traduits en respectant la ponctuation
     */
    private function joinTranslatedChunks(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $result = '';
        foreach ($chunks as $index => $chunk) {
            if ($index === 0) {
                $result = $chunk;
            } else {
                // Vérifier si le chunk précédent finit par une ponctuation
                if (preg_match('/[.!?;:]\s*$/', $result)) {
                    // Ponctuation trouvée, ajouter un espace simple
                    $result .= ' ' . $chunk;
                } else if (preg_match('/[,]\s*$/', $result)) {
                    // Virgule, ajouter un espace simple
                    $result .= ' ' . $chunk;
                } else {
                    // Pas de ponctuation, ajouter directement (devrait être rare)
                    $result .= ' ' . $chunk;
                }
            }
        }

        return $result;
    }

    /**
     * Service de traduction gratuit (MyMemory) - MEILLEUR POUR LANGUES COMPLEXES
     */
    private function translateWithFreeService(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        try {
            // Normaliser les codes de langue
            $sourceCode = $this->normalizeLangCode($sourceLanguage);
            $targetCode = $this->normalizeLangCode($targetLanguage);

            // Mapper les codes pour MyMemory (qui peut avoir des codes différents)
            $myMemoryLangMap = [
                'ar' => 'ar-SA',     // Arabic (Saudi Arabia)
                'zh' => 'zh-CN',     // Chinese (Simplified)
                'pt' => 'pt-BR',     // Portuguese (Brazil)
            ];
            
            $mappedTarget = $myMemoryLangMap[$targetCode] ?? $targetCode;
            $mappedSource = $sourceCode === 'auto' ? '|' : ($myMemoryLangMap[$sourceCode] ?? $sourceCode);

            // MyMemory free tier works better with GET for small chunks
            $response = $this->client->request('GET', 'https://api.mymemory.translated.net/get', [
                'query' => [
                    'q' => $text,
                    'langpair' => $mappedSource . '|' . $mappedTarget,
                ],
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (FinTrack Translation Service)',
                ]
            ]);

            $data = $response->toArray(false);
            
            // Meilleure gestion de la réponse MyMemory
            if (isset($data['responseStatus']) && $data['responseStatus'] == 200) {
                if (isset($data['responseData']['translatedText']) && !empty($data['responseData']['translatedText'])) {
                    $result = html_entity_decode($data['responseData']['translatedText']);
                    
                    // Vérifier que c'est une vraie traduction et pas un message d'erreur
                    $isRTL = in_array($targetCode, ['ar', 'he', 'ur', 'fa']);
                    
                    // Pour les langues RTL, accepter les résultats plus courts
                    $minLength = $isRTL ? 2 : 3;
                    
                    if (strlen($result) > $minLength && 
                        strpos($result, 'Sorry') === false &&
                        strpos($result, 'ERROR') === false) {
                        $this->logger->info("MyMemory translation successful for language $targetCode");
                        return $result;
                    }
                }
            }
            
            if (isset($data['responseStatus']) && $data['responseStatus'] != 200) {
                $this->logger->info('MyMemory returned status ' . $data['responseStatus'] . ': ' . ($data['responseDetails'] ?? 'No details'));
            }
        } catch (\Exception $e) {
            $this->logger->warning('MyMemory API Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Divise un texte en morceaux en respectant les fins de phrases et paragraphes
     */
    private function splitTextIntoChunks(string $text, int $maxLength): array
    {
        if (strlen($text) <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        
        // D'abord, essayer de diviser par paragraphes (double newline)
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (count($paragraphs) > 1) {
            $currentChunk = '';
            
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                
                // Si le paragraphe seul est plus long que maxLength
                if (strlen($paragraph) > $maxLength) {
                    // Ajouter le chunk courant s'il existe
                    if (!empty($currentChunk)) {
                        $chunks[] = trim($currentChunk);
                        $currentChunk = '';
                    }
                    
                    // Diviser le paragraphe par phrases
                    $sentences = preg_split('/(?<=[.?!])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($sentences as $sentence) {
                        if (strlen($sentence) > $maxLength) {
                            // Phrase trop longue, la couper par mots
                            $words = explode(' ', $sentence);
                            $sentenceChunk = '';
                            foreach ($words as $word) {
                                if (strlen($sentenceChunk . ' ' . $word) > $maxLength) {
                                    if (!empty($sentenceChunk)) {
                                        $chunks[] = trim($sentenceChunk);
                                    }
                                    $sentenceChunk = $word;
                                } else {
                                    $sentenceChunk .= (empty($sentenceChunk) ? '' : ' ') . $word;
                                }
                            }
                            if (!empty($sentenceChunk)) {
                                $chunks[] = trim($sentenceChunk);
                            }
                        } else {
                            // Phrase normale
                            if (strlen($currentChunk . ' ' . $sentence) > $maxLength) {
                                if (!empty($currentChunk)) {
                                    $chunks[] = trim($currentChunk);
                                }
                                $currentChunk = $sentence;
                            } else {
                                $currentChunk .= (empty($currentChunk) ? '' : ' ') . $sentence;
                            }
                        }
                    }
                } else {
                    // Paragraphe de taille normale
                    if (strlen($currentChunk . "\n\n" . $paragraph) > $maxLength) {
                        if (!empty($currentChunk)) {
                            $chunks[] = trim($currentChunk);
                        }
                        $currentChunk = $paragraph;
                    } else {
                        $currentChunk .= (empty($currentChunk) ? '' : "\n\n") . $paragraph;
                    }
                }
            }
            
            if (!empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
            }
        } else {
            // Pas de paragraphes multiples, diviser par phrases
            $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            $currentChunk = '';

            foreach ($sentences as $sentence) {
                if (strlen($currentChunk . ' ' . $sentence) > $maxLength) {
                    if (!empty($currentChunk)) {
                        $chunks[] = trim($currentChunk);
                    }
                    
                    // Si une seule phrase est plus longue que maxLength
                    if (strlen($sentence) > $maxLength) {
                        $words = explode(' ', $sentence);
                        $wordChunk = '';
                        foreach ($words as $word) {
                            if (strlen($wordChunk . ' ' . $word) > $maxLength) {
                                if (!empty($wordChunk)) {
                                    $chunks[] = trim($wordChunk);
                                }
                                $wordChunk = $word;
                            } else {
                                $wordChunk .= (empty($wordChunk) ? '' : ' ') . $word;
                            }
                        }
                        if (!empty($wordChunk)) {
                            $chunks[] = trim($wordChunk);
                        }
                        $currentChunk = '';
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
        }

        return array_filter($chunks, fn($chunk) => !empty($chunk));
    }

    /**
     * Fallback: Lingva Translate (Instance publique Libre) - MEILLEURE QUALITÉ
     */
    private function translateWithLingva(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        // Liste d'instances Lingva pour plus de robustesse (ordonnées par fiabilité)
        // Certaines instances peuvent être meilleures pour certaines langues
        $instances = [
            'https://lingva.ml',                      // Plus stable
            'https://translate.plausibility.cloud',   // Bon pour langues complexes
            'https://lingva.garudalinux.org',
            'https://lingva.lunar.icu',
        ];

        $source = $sourceLanguage === 'auto' ? 'auto' : $this->normalizeLangCode($sourceLanguage);
        $target = $this->normalizeLangCode($targetLanguage);
        
        // Vérifier le support de la langue cible
        $supportedLangs = $this->getSupportedLanguages();
        if (!isset($supportedLangs[$target])) {
            $this->logger->warning("Unsupported target language: $target");
            return null;
        }

        foreach ($instances as $baseUrl) {
            try {
                // Lingva utilise un format spécial: /api/v1/[source]/[target]/[query]
                $url = sprintf(
                    '%s/api/v1/%s/%s/%s',
                    $baseUrl,
                    $source,
                    $target,
                    urlencode($text)
                );

                $response = $this->client->request('GET', $url, [
                    'timeout' => 8,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (FinTrack Translation Service)',
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray(false);
                    if (isset($data['translation']) && !empty($data['translation'])) {
                        $result = trim($data['translation']);
                        // Vérifier que ce n'est pas un message d'erreur
                        // Pour l'arabe et autres langues RTL, on accepte plus de résultats
                        $isRTL = in_array($target, ['ar', 'he', 'ur', 'fa']);
                        $minLength = $isRTL ? 2 : 3;
                        
                        if (strlen($result) > $minLength && 
                            strpos(strtolower($result), 'error') === false &&
                            strpos($result, 'Not Found') === false) {
                            $this->logger->info("Lingva translation successful from $baseUrl for language $target");
                            return $result;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug("Lingva instance $baseUrl failed for language $target: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /**
     * Google Translate API (payant) - MEILLEUR POUR LANGUES COMPLEXES
     */
    private function translateWithGoogleAPI(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): ?string
    {
        try {
            $normalizedTarget = $this->normalizeLangCode($targetLanguage);
            $normalizedSource = $sourceLanguage === 'auto' ? 'auto' : $this->normalizeLangCode($sourceLanguage);
            
            $response = $this->client->request('POST', 'https://translation.googleapis.com/language/translate/v2', [
                'query' => ['key' => $this->translationApiKey],
                'json' => [
                    'q' => $text,
                    'target' => $normalizedTarget,
                    'source' => $normalizedSource !== 'auto' ? $normalizedSource : '',
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray();
            
            if (isset($data['data']['translations'][0]['translatedText'])) {
                $result = html_entity_decode($data['data']['translations'][0]['translatedText']);
                $this->logger->info("Google Translate API successful for $normalizedTarget");
                return $result;
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
     * Normalise les codes de langue pour assurer la compatibilité avec tous les services
     */
    private function normalizeLangCode(string $langCode): string
    {
        // Mapper les codes complets vers les codes ISO 639-1
        $mapping = [
            'english' => 'en',
            'french' => 'fr',
            'spanish' => 'es',
            'german' => 'de',
            'italian' => 'it',
            'portuguese' => 'pt',
            'russian' => 'ru',
            'japanese' => 'ja',
            'chinese' => 'zh',
            'arabic' => 'ar',
            'korean' => 'ko',
            'turkish' => 'tr',
            'dutch' => 'nl',
            'polish' => 'pl',
            'vietnamese' => 'vi',
            'hebrew' => 'he',
            'farsi' => 'fa',
            'urdu' => 'ur',
            'thai' => 'th',
        ];

        $lower = strtolower(trim($langCode));
        
        // Si c'est un code connu, utiliser le mapping
        if (isset($mapping[$lower])) {
            return $mapping[$lower];
        }

        // Si c'est déjà un code 2 lettres valide
        if (strlen($lower) === 2 && ctype_alpha($lower)) {
            return $lower;
        }

        // Si c'est un code 5 lettres (e.g., fr_FR, ar-SA), prendre les 2 premières lettres
        if (strlen($lower) >= 2) {
            $code = substr($lower, 0, 2);
            if (ctype_alpha($code)) {
                return $code;
            }
        }

        // Défaut: Français
        return 'fr';
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
            'he' => 'עברית',
            'fa' => 'فارسی',
            'ur' => 'اردو',
            'th' => 'ไทย',
        ];
    }
}
