<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour interagir avec l'API Groq et fournir des conseils financiers.
 * Groq est gratuit, très rapide et compatible OpenAI.
 */
class AIService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, string $groqApiKey, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $groqApiKey;
        $this->logger = $logger;
    }

    /**
     * Génère des conseils financiers basés sur les dépenses et budgets via Groq.
     */
    public function getFinancialAdvice(array $expenses, array $budgets, float $totalBalance): string
    {
        if ($this->apiKey === 'your_groq_api_key_here' || $this->apiKey === 'not_set' || empty($this->apiKey)) {
            return "⚠️ **Configuration requise** : Veuillez configurer votre clé API Groq (`GROQ_API_KEY`) dans le fichier `.env` pour activer le conseiller financier IA.";
        }

        // Préparation du résumé des dépenses (limitées aux 30 dernières pour le contexte)
        $expenseSummary = "";
        $limitedExpenses = array_slice($expenses, 0, 30);
        foreach ($limitedExpenses as $e) {
            $date = $e['date_depense'] ?? 'Inconnue';
            $cat = $e['categorie'] ?? ($e['nom_categorie'] ?? 'Inconnue');
            $expenseSummary .= "- {$date} : {$cat} - {$e['montant']} TND ({$e['description']})\n";
        }

        // Préparation du résumé des budgets
        $budgetSummary = "";
        foreach ($budgets as $b) {
            $budgetSummary .= "- {$b['nom_budget']} : {$b['montant_total']} TND\n";
        }

        // Construction du prompt en français
        $prompt = "Tu es FinTrack AI, un conseiller financier expert, amical et très motivant. 
        Voici les données financières actuelles de l'utilisateur :
        
        - SOLDE TOTAL ACTUEL : {$totalBalance} TND
        
        - BUDGETS DÉFINIS :
        {$budgetSummary}
        
        - DERNIÈRES DÉPENSES EFFECTUÉES :
        {$expenseSummary}

        Analyse précisément ces données et donne :
        1. Un bref récapitulatif de sa santé financière.
        2. 3 conseils concrets et personnalisés pour économiser dès maintenant sur ses plus gros postes de dépenses.
        3. Un petit message d'encouragement dynamique.

        Réponds en français, utilise un ton chaleureux, et utilise le format Markdown (listes, gras) avec des emojis.";

        try {
            // Appel à Groq API (format OpenAI-compatible)
            $url = "https://api.groq.com/openai/v1/chat/completions";
            
            $this->logger->info("Appel Groq AI pour conseils financiers");

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant', // Modèle Groq actuel et disponible, ultra-rapide et gratuit
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1024,
                ],
                'timeout' => 10
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 200) {
                $data = $response->toArray();
                if (isset($data['choices'][0]['message']['content'])) {
                    return $data['choices'][0]['message']['content'];
                }
            }

            $errorData = $response->toArray(false);
            $errorMsg = $errorData['error']['message'] ?? 'Erreur inconnue';
            $this->logger->warning("Échec Groq AI : " . $errorMsg);
            
            return "❌ **Erreur Groq AI** : Impossible de générer les conseils. Erreur: \"$errorMsg\"";

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->logger->error("Exception Groq AI : " . $errorMsg);
            return "❌ **Erreur Groq AI** : " . $errorMsg;
        }
    }

    /**
     * Test simple de la clé API Groq
     */
    public function testApiKey(): array
    {
        $url = "https://api.groq.com/openai/v1/models";
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ]
            ]);
            $statusCode = $response->getStatusCode();
            return [
                'success' => $statusCode === 200,
                'status' => $statusCode,
                'data' => $response->toArray(false)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

