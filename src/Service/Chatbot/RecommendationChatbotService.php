<?php

namespace App\Service\Chatbot;

use App\Entity\User;
use App\Service\Recommendation\HybridRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RecommendationChatbotService
{
    public function __construct(
        private HybridRecommendationService $recommendationService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private HttpClientInterface $httpClient,
        private string $groqApiKey
    ) {
    }

    /**
     * Traite une question utilisateur et retourne une réponse du chatbot
     */
    public function processMessage(User $user, string $message): array
    {
        $rawMessage = trim($message);
        $message = mb_strtolower($rawMessage);
        
        $this->logger->info('Chatbot.processMessage: Starting', [
            'user_id' => $user->getId(),
            'message' => $message
        ]);

        $context = $this->buildUserContext($user);
        $this->logger->info('Chatbot.processMessage: Context built', ['context_size' => strlen(json_encode($context))]);

        // Analyser l'intention de l'utilisateur
        $intent = $this->detectIntent($message);
        $this->logger->info('Chatbot.processMessage: Intent detected', ['intent' => $intent]);

        switch ($intent) {
            case 'recommendations':
                $this->logger->info('Chatbot: Handling recommendations');
                $result = $this->handleRecommendationsRequest($user, $message);
                break;
                
            case 'category_filter':
                $this->logger->info('Chatbot: Handling category filter');
                $result = $this->handleCategoryFilter($user, $message);
                break;
                
            case 'service_info':
                $this->logger->info('Chatbot: Handling service info');
                $result = $this->handleServiceInfo($message);
                break;
                
            case 'help':
                $result = $this->handleHelp();
                break;
                
            case 'greeting':
                $result = $this->handleGreeting($user);
                break;
                
            case 'price_comparison':
                $result = $this->handlePriceComparison($user);
                break;
                
            default:
                return $this->handleConversationalAi($user, $rawMessage, $context);
        }

        // Améliorer le texte de réponse avec Groq pour éviter un bot trop statique.
        $enhancedMessage = $this->enhanceResponseWithAi($rawMessage, (string) ($result['message'] ?? ''), $context);
        if ($enhancedMessage !== null) {
            $result['message'] = $enhancedMessage;
        }

        return $result;
    }

    /**
     * Détecte l'intention de l'utilisateur à partir du message
     */
    private function detectIntent(string $message): string
    {
        // Mots-clés pour chaque intention
        $intents = [
            'recommendations' => ['recommandation', 'recommande', 'suggère', 'propose', 'meilleur', 'top'],
            'category_filter' => ['abonnement', 'facture', 'catégorie', 'type', 'filtre'],
            'service_info' => ['information', 'détail', 'prix', 'coût', 'tarif', 'description'],
            'help' => ['aide', 'comment', 'utiliser', 'fonctionne', 'guide'],
            'greeting' => ['bonjour', 'salut', 'hello', 'hi', 'bienvenue'],
            'price_comparison' => ['comparer', 'prix', 'moins cher', 'économique', 'budget']
        ];

        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    return $intent;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Gère les demandes de recommandations
     */
    private function handleRecommendationsRequest(User $user, string $message): array
    {
        $this->logger->info('handleRecommendationsRequest: Starting');
        try {
            // Extraire le nombre de recommandations demandées
            $limit = $this->extractLimit($message);
            $this->logger->info('handleRecommendationsRequest: Extracted limit', ['limit' => $limit]);
            
            $recommendations = $this->recommendationService->getRecommendations($user, $limit);
            $this->logger->info('handleRecommendationsRequest: Got recommendations', ['count' => count($recommendations)]);

            if (empty($recommendations)) {
                $this->logger->info('handleRecommendationsRequest: No recommendations found');
                return [
                    'type' => 'recommendations',
                    'message' => 'Je n\'ai pas trouvé de recommandations pour vous. Commencez à interagir avec des services pour obtenir des suggestions personnalisées !',
                    'data' => []
                ];
            }

            $responseText = "Voici mes meilleures recommandations pour vous :\n\n";
            $services = [];

            foreach ($recommendations as $recommendation) {
                $service = $recommendation['service'];
                $score = round($recommendation['score'] * 100);
                
                $responseText .= "## {$service->getNomService()}\n";
                $responseText .= "- **Type** : {$service->getTypeService()}\n";
                $responseText .= "- **Tarif** : {$service->getTarif()} DT";
                $responseText .= $service->getFrequence() ? " / {$service->getFrequence()}" : "";
                $responseText .= "\n- **Score** : {$score}%\n\n";

                $services[] = [
                    'id' => $service->getId(),
                    'name' => $service->getNomService(),
                    'type' => $service->getTypeService(),
                    'tarif' => $service->getTarif(),
                    'score' => $score
                ];
            }

            return [
                'type' => 'recommendations',
                'message' => $responseText,
                'data' => $services
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting recommendations', ['error' => $e->getMessage()]);
            
            return [
                'type' => 'error',
                'message' => 'Désolé, je n\'ai pas pu récupérer les recommandations. Réessayez plus tard.',
                'data' => []
            ];
        }
    }

    /**
     * Gère les filtres par catégorie
     */
    private function handleCategoryFilter(User $user, string $message): array
    {
        $category = null;
        
        if (str_contains($message, 'abonnement')) {
            $category = 'abonnement';
        } elseif (str_contains($message, 'facture')) {
            $category = 'facture';
        }

        if (!$category) {
            return [
                'type' => 'category_filter',
                'message' => 'Veuillez spécifier une catégorie : "abonnement" ou "facture"',
                'data' => []
            ];
        }

        try {
            $services = $this->entityManager->getRepository(\App\Entity\Service::class)
                ->findBy(['user' => $user, 'typeService' => $category, 'statut' => 'actif'], ['nomService' => 'ASC'], 5);

            if (empty($services)) {
                return [
                    'type' => 'category_filter',
                    'message' => "Aucun service trouvé dans la catégorie '{$category}'",
                    'data' => []
                ];
            }

            $responseText = "Services de type **{$category}** :\n\n";
            $filteredServices = [];

            foreach ($services as $service) {
                $responseText .= "- **{$service->getNomService()}** : {$service->getTarif()} DT";
                $responseText .= $service->getFrequence() ? " / {$service->getFrequence()}" : "";
                $responseText .= "\n";

                $filteredServices[] = [
                    'id' => $service->getId(),
                    'name' => $service->getNomService(),
                    'type' => $service->getTypeService(),
                    'tarif' => $service->getTarif()
                ];
            }

            return [
                'type' => 'category_filter',
                'message' => $responseText,
                'data' => $filteredServices
            ];

        } catch (\Exception $e) {
            return [
                'type' => 'error',
                'message' => 'Erreur lors de la recherche par catégorie',
                'data' => []
            ];
        }
    }

    /**
     * Gère les demandes d'information sur les services
     */
    private function handleServiceInfo(string $message): array
    {
        return [
            'type' => 'service_info',
            'message' => "Je peux vous aider avec :\n\n" .
                       "- **Recommandations personnalisées** : demandez \"recommande-moi des services\"\n" .
                       "- **Filtrage par catégorie** : demandez \"montre les abonnements\" ou \"montre les factures\"\n" .
                       "- **Comparaison de prix** : demandez \"compare les prix\"\n" .
                       "- **Aide** : demandez \"aide\" pour voir toutes les commandes",
            'data' => []
        ];
    }

    /**
     * Gère les demandes d'aide
     */
    private function handleHelp(): array
    {
        return [
            'type' => 'help',
            'message' => "## Commandes disponibles :\n\n" .
                       "### Recommandations\n" .
                       "- \"recommande-moi des services\" - Top 5 recommandations\n" .
                       "- \"meilleures recommandations\" - Top 3 recommandations\n\n" .
                       "### Filtres\n" .
                       "- \"montre les abonnements\" - Services d'abonnement\n" .
                       "- \"montre les factures\" - Services de facturation\n\n" .
                       "### Comparaisons\n" .
                       "- \"compare les prix\" - Services les moins chers\n" .
                       "- \"meilleurs prix\" - Rapport qualité/prix\n\n" .
                       "### Autres\n" .
                       "- \"aide\" - Afficher cette aide\n" .
                       "- \"bonjour\" - Pour commencer !",
            'data' => []
        ];
    }

    /**
     * Gère les salutations
     */
    private function handleGreeting(User $user): array
    {
        $userName = $user->getFullName() ?? 'utilisateur';
        
        return [
            'type' => 'greeting',
            'message' => "Bonjour {$userName} ! Je suis votre assistant de recommandation de services.\n\n" .
                       "Je peux vous aider à :\n" .
                       "- Trouver les meilleurs services pour vous\n" .
                       "- Filtrer par catégorie (abonnements/factures)\n" .
                       "- Comparer les prix\n\n" .
                       "Demandez-moi \"aide\" pour voir toutes les commandes disponibles !",
            'data' => []
        ];
    }

    /**
     * Gère les comparaisons de prix
     */
    private function handlePriceComparison(User $user): array
    {
        try {
            $services = $this->entityManager->getRepository(\App\Entity\Service::class)
                ->findBy(['user' => $user, 'statut' => 'actif'], ['tarif' => 'ASC'], 5);

            if (empty($services)) {
                return [
                    'type' => 'price_comparison',
                    'message' => 'Aucun service actif trouvé pour la comparaison',
                    'data' => []
                ];
            }

            $responseText = "Services les moins chers :\n\n";
            $cheapestServices = [];

            foreach ($services as $service) {
                $responseText .= "- **{$service->getNomService()}** : {$service->getTarif()} DT";
                $responseText .= $service->getFrequence() ? " / {$service->getFrequence()}" : "";
                $responseText .= " ({$service->getTypeService()})\n";

                $cheapestServices[] = [
                    'id' => $service->getId(),
                    'name' => $service->getNomService(),
                    'tarif' => $service->getTarif(),
                    'type' => $service->getTypeService()
                ];
            }

            return [
                'type' => 'price_comparison',
                'message' => $responseText,
                'data' => $cheapestServices
            ];

        } catch (\Exception $e) {
            return [
                'type' => 'error',
                'message' => 'Erreur lors de la comparaison des prix',
                'data' => []
            ];
        }
    }

    /**
     * Gère les messages inconnus
     */
    private function handleUnknown(string $message): array
    {
        return [
            'type' => 'unknown',
            'message' => "Je ne suis pas sûr de comprendre. Essayez de demander :\n\n" .
                       "- \"aide\" pour voir les commandes disponibles\n" .
                       "- \"recommande-moi des services\" pour des suggestions\n" .
                       "- \"montre les abonnements\" pour filtrer par catégorie",
            'data' => []
        ];
    }

    /**
     * Extrait la limite de recommandations du message
     */
    private function extractLimit(string $message): int
    {
        if (preg_match('/(\d+)/', $message, $matches)) {
            $limit = (int) $matches[1];
            return min(10, max(1, $limit));
        }
        
        return 5; // Par défaut
    }

    /**
     * Réponse conversationnelle libre basée sur Groq + données utilisateur.
     */
    private function handleConversationalAi(User $user, string $userMessage, array $context): array
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $systemPrompt = <<<'PROMPT'
Tu es l'assistant FinTrack pour les recommandations de services et produits.
Tu réponds en français, de manière concise et utile.
Tu dois te baser uniquement sur les données utilisateur fournies dans le contexte JSON.
Si une information n'est pas dans le contexte, dis-le clairement et propose l'action à faire.
Ne divulgue jamais de secrets, tokens, API keys, ni données sensibles.
PROMPT;

        $userPrompt = "Question utilisateur: \n" . $userMessage . "\n\n" .
            "Contexte utilisateur (JSON):\n" . ($contextJson ?: '{}') . "\n\n" .
            "Objectif: répondre en texte clair + éventuellement une courte liste d'actions.";

        $aiResponse = $this->callGroq($systemPrompt, $userPrompt, 800);

        if ($aiResponse === null || trim($aiResponse) === '') {
            return $this->handleUnknown(mb_strtolower($userMessage));
        }

        return [
            'type' => 'ai_chat',
            'message' => $aiResponse,
            'data' => []
        ];
    }

    /**
     * Rend les réponses déterministes plus naturelles grâce à Groq.
     */
    private function enhanceResponseWithAi(string $question, string $baseAnswer, array $context): ?string
    {
        $baseAnswer = trim($baseAnswer);
        if ($baseAnswer === '') {
            return null;
        }

        $contextSummary = [
            'stats' => $context['stats'] ?? [],
            'services_preview' => array_slice((array) ($context['services'] ?? []), 0, 5),
            'products_preview' => array_slice((array) ($context['products'] ?? []), 0, 5),
        ];

        $systemPrompt = <<<'PROMPT'
Tu reformules la réponse d'un assistant FinTrack.
Règles:
- Répondre en français naturel.
- Garder les infos essentielles de la réponse initiale.
- Ajouter une personnalisation légère basée sur le contexte utilisateur.
- Ne pas inventer des données non présentes.
- Sortie en texte simple compatible markdown léger.
PROMPT;

        $userPrompt = "Question: \n{$question}\n\n" .
            "Réponse initiale: \n{$baseAnswer}\n\n" .
            "Contexte: \n" . json_encode($contextSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $this->callGroq($systemPrompt, $userPrompt, 700);
    }

    /**
     * Appel Groq (compatible API chat OpenAI).
     */
    private function callGroq(string $systemPrompt, string $userPrompt, int $maxTokens = 700): ?string
    {
        $this->logger->info('callGroq: Starting', ['max_tokens' => $maxTokens]);
        
        $apiKey = trim($this->groqApiKey);
        $this->logger->info('callGroq: API key length', ['length' => strlen($apiKey)]);
        
        if ($apiKey === '' || $apiKey === 'not_set' || $apiKey === 'your_groq_api_key_here') {
            $this->logger->warning('callGroq: Invalid API key');
            return null;
        }

        try {
            $this->logger->info('callGroq: Making HTTP request');
            
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.35,
                    'max_tokens' => $maxTokens,
                ],
                'timeout' => 12,
            ]);

            $this->logger->info('callGroq: Got response', ['status_code' => $response->getStatusCode()]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('callGroq: Non-200 status code', ['status' => $response->getStatusCode()]);
                return null;
            }

            $payload = $response->toArray(false);
            $content = (string) ($payload['choices'][0]['message']['content'] ?? '');
            $content = trim($content);

            $this->logger->info('callGroq: Success', ['content_length' => strlen($content)]);
            return $content !== '' ? $content : null;
        } catch (\Throwable $exception) {
            $this->logger->error('callGroq: Exception', [
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
            ]);

            return null;
        }
    }

    /**
     * Construit le contexte utilisateur (services + produits + stats) pour Groq.
     */
    private function buildUserContext(User $user): array
    {
        $connection = $this->entityManager->getConnection();

        try {
            $services = $connection->fetchAllAssociative(
                'SELECT id_service AS id, nom_service AS name, tarif, type_service AS type, frequence, statut
                 FROM service
                 WHERE user_id = :user_id
                 ORDER BY id_service DESC
                 LIMIT 20',
                ['user_id' => $user->getId()]
            );

            $products = $connection->fetchAllAssociative(
                'SELECT id_produit AS id, nom_produit AS name, montant, type_produit AS type, statut
                 FROM produit
                 WHERE user_id = :user_id
                 ORDER BY id_produit DESC
                 LIMIT 20',
                ['user_id' => $user->getId()]
            );

            $serviceStats = $connection->fetchAssociative(
                'SELECT COUNT(*) AS total_services,
                        COALESCE(SUM(CASE WHEN statut = "actif" THEN 1 ELSE 0 END), 0) AS active_services,
                        COALESCE(SUM(CAST(tarif AS DECIMAL(15,2))), 0) AS services_total_tarif
                 FROM service
                 WHERE user_id = :user_id',
                ['user_id' => $user->getId()]
            ) ?: [];

            $productStats = $connection->fetchAssociative(
                'SELECT COUNT(*) AS total_products,
                        COALESCE(SUM(CASE WHEN statut = "disponible" THEN 1 ELSE 0 END), 0) AS available_products,
                        COALESCE(SUM(CAST(montant AS DECIMAL(15,2))), 0) AS products_total_amount
                 FROM produit
                 WHERE user_id = :user_id',
                ['user_id' => $user->getId()]
            ) ?: [];

            return [
                'user' => [
                    'id' => $user->getId(),
                    'full_name' => $user->getFullName(),
                ],
                'stats' => [
                    'total_services' => (int) ($serviceStats['total_services'] ?? 0),
                    'active_services' => (int) ($serviceStats['active_services'] ?? 0),
                    'services_total_tarif' => (float) ($serviceStats['services_total_tarif'] ?? 0),
                    'total_products' => (int) ($productStats['total_products'] ?? 0),
                    'available_products' => (int) ($productStats['available_products'] ?? 0),
                    'products_total_amount' => (float) ($productStats['products_total_amount'] ?? 0),
                ],
                'services' => $services,
                'products' => $products,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to build recommendation chatbot context', [
                'user_id' => $user->getId(),
                'error' => $exception->getMessage(),
            ]);

            return [
                'user' => [
                    'id' => $user->getId(),
                    'full_name' => $user->getFullName(),
                ],
                'stats' => [],
                'services' => [],
                'products' => [],
            ];
        }
    }
}
