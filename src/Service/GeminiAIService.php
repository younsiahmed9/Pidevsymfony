<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour interagir avec l'API Google Gemini spécifiquement pour l'intelligence documentaire.
 */
class GeminiAIService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $geminiApiKey;
        $this->logger = $logger;
    }

    /**
     * Indique si la clé Gemini est disponible.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'not_set';
    }

    /**
     * Analyse un texte OCR pour en extraire des informations structurées via Gemini.
     */
    public function analyzeDocumentText(string $text): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'GEMINI_API_KEY non configurée'];
        }

        $prompt = "Tu es un expert en analyse de documents. Voici le texte extrait par OCR d'un document :
        
        \"\"\"
        {$text}
        \"\"\"
        
        Analyse ce texte et retourne UNIQUEMENT un objet JSON avec les champs suivants :
        - title : Un titre court et descriptif pour ce document.
        - category : La catégorie la plus probable (Facture, Contrat, Identité, Médical, Scolaire, Autre).
        - summary : Un résumé en une phrase.
        - date_document : La date d'émission au format YYYY-MM-DD (si trouvée).
        - date_echeance : La date d'échéance ou expiration au format YYYY-MM-DD (si applicable).
        - amount : Le montant total TTC avec la devise (si c'est une facture).
        - tags : Une liste de 3 à 5 mots-clés séparés par des virgules.
        
        Réponds uniquement avec le JSON, sans texte avant ou après.";

        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $this->apiKey;
            
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ]
            ]);

            $data = $response->toArray();
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            // Nettoyage du JSON si Gemini ajoute des backticks
            $responseText = preg_replace('/^```json\s*|\s*```$/i', '', trim($responseText));
            
            return json_decode($responseText, true) ?? ['error' => 'Réponse JSON invalide'];

        } catch (\Exception $e) {
            $this->logger->error('Gemini Document Analysis Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Chat intelligent avec le contenu d'un document.
     */
    public function chatWithDocument(string $documentText, string $question): string
    {
        if (!$this->isConfigured()) {
            return "Désolé, l'IA n'est pas configurée (GEMINI_API_KEY manquante).";
        }

        $prompt = "Voici le contenu d'un document :
        \"\"\"
        {$documentText}
        \"\"\"
        
        Question de l'utilisateur : \"{$question}\"
        
        Réponds de manière concise et précise en te basant uniquement sur le document. Si la réponse n'est pas dans le document, dis-le poliment.";

        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $this->apiKey;
            
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ]
            ]);

            $data = $response->toArray();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? "Je n'ai pas pu générer de réponse.";

        } catch (\Exception $e) {
            $this->logger->error('Gemini Document Chat Error: ' . $e->getMessage());
            return "Une erreur est survenue lors de la communication avec l'IA.";
        }
    }
}
