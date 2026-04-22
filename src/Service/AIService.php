<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour interagir avec l'API Google Gemini et fournir des conseils financiers.
 */
class AIService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $geminiApiKey;
    }

    /**
     * Génère des conseils financiers basés sur les dépenses et budgets via Google Gemini.
     */
    public function getFinancialAdvice(array $expenses, array $budgets, float $totalBalance): string
    {
        if ($this->apiKey === 'your_gemini_api_key_here' || $this->apiKey === 'not_set' || empty($this->apiKey)) {
            return "⚠️ **Configuration requise** : Veuillez configurer votre clé API Gemini (`GEMINI_API_KEY`) dans le fichier `.env` pour activer le conseiller financier IA.";
        }

        // Préparation du résumé des dépenses (limitées aux 30 dernières pour le contexte)
        $expenseSummary = "";
        $limitedExpenses = array_slice($expenses, 0, 30);
        foreach ($limitedExpenses as $e) {
            $date = $e['date_depense'] ?? 'Inconnue';
            $expenseSummary .= "- {$date} : {$e['categorie']} - {$e['montant']} TND ({$e['description']})\n";
        }

        // Préparation du résumé des budgets
        $budgetSummary = "";
        foreach ($budgets as $b) {
            $budgetSummary .= "- {$b['nom_budget']} : {$b['montant_total']} TND\n";
        }

        // Construction du prompt
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
            // URL de l'API Google Gemini (Modèle à jour pour 2026)
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $this->apiKey;

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 1024,
                    ]
                ],
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                $errorData = $response->toArray(false);
                $msg = $errorData['error']['message'] ?? 'Erreur inconnue';
                return "❌ **Erreur Gemini AI** : Google a répondu avec l'erreur : \"$msg\" (Code HTTP: $statusCode)";
            }

            $data = $response->toArray();
            
            // Extraction de la réponse du format spécifique à Gemini
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }

            return "🤖 Désolé, je n'ai pas pu générer de conseils pour le moment. Veuillez vérifier la configuration de votre modèle Gemini.";

        } catch (\Exception $e) {
            return "❌ **Erreur d'accès à l'IA** : " . $e->getMessage();
        }
    }
}
