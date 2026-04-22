<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service pour discuter avec un document (Q&A)
 */
class DocumentChatService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Analyse la question et cherche la réponse dans le texte du document
     */
    public function askQuestion(string $documentText, string $question): string
    {
        if (empty(trim($documentText))) {
            return "Désolé, je ne peux pas répondre car le contenu du document est vide ou n'a pas pu être extrait.";
        }

        $question = mb_strtolower(trim($question));
        $documentTextLower = mb_strtolower($documentText);

        // 1. Détection des intentions courantes
        
        // Signataires / Personnes
        if ($this->isMatch($question, ['signataire', 'signé', 'qui', 'personne', 'nom', 'auteur'])) {
            return $this->extractSignatory($documentText);
        }

        // Montants / Argent
        if ($this->isMatch($question, ['montant', 'prix', 'total', 'euro', 'payer', 'tva', 'argent', 'coût', 'somme'])) {
            return $this->extractAmount($documentText);
        }

        // Dates / Échéances
        if ($this->isMatch($question, ['date', 'quand', 'échéance', 'expiration', 'validité', 'période'])) {
            return $this->extractDateInfo($documentText);
        }

        // Type de document
        if ($this->isMatch($question, ['quoi', 'quel type', 'nature', 'document'])) {
            return $this->extractDocumentType($documentText);
        }

        // 2. Recherche contextuelle générique
        return $this->findContextualAnswer($documentText, $question);
    }

    /**
     * Vérifie si la question contient des mots clés
     */
    private function isMatch(string $question, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($question, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tente d'extraire le signataire
     */
    private function extractSignatory(string $text): string
    {
        // Chercher "Fait à...", "Signé par...", "M. ", "Mme "
        $patterns = [
            '/signé par\s+:?\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i',
            '/M\.\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/',
            '/Mme\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/',
            '/Directeur\s+:?\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return "D'après mon analyse, le signataire ou la personne mentionnée est **" . $matches[1] . "**. C'est souvent indiqué en bas du document.";
            }
        }

        return "Je n'ai pas pu identifier de signataire précis, mais le document semble émaner de l'entité mentionnée dans l'en-tête.";
    }

    /**
     * Tente d'extraire les montants
     */
    private function extractAmount(string $text): string
    {
        $patterns = [
            '/(?:total|montant|somme)(?:\s+(?:ttc|ht))?\s*:?\s*([\d\s,.]+\s*(?:€|euro|dt|usd))/i',
            '/([\d\s,.]+\s*(?:€|euro|dt|usd))/i'
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $m) {
                    $found[] = trim($m);
                }
            }
        }

        if (!empty($found)) {
            $uniqueFound = array_unique($found);
            return "Le document mentionne les montants suivants : **" . implode(', ', array_slice($uniqueFound, 0, 3)) . "**. Le montant total figure généralement vers la fin.";
        }

        return "Aucun montant financier précis n'a été détecté avec certitude dans ce document.";
    }

    /**
     * Tente d'extraire les informations de date
     */
    private function extractDateInfo(string $text): string
    {
        $patterns = [
            '/\b(\d{1,2}[\/\-. ]\d{1,2}[\/\-. ]\d{4})\b/',
            '/date\s+:?\s*(\d{1,2}[\/\-. ]\d{1,2}[\/\-. ]\d{4})/i',
            '/échéance\s+:?\s*(\d{1,2}[\/\-. ]\d{1,2}[\/\-. ]\d{4})/i'
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $m) {
                    $found[] = trim($m);
                }
            }
        }

        if (!empty($found)) {
            return "J'ai trouvé ces dates importantes : **" . implode(', ', array_unique($found)) . "**. La date d'émission est souvent en haut à droite.";
        }

        return "Je n'ai pas trouvé de date formatée de type JJ/MM/AAAA dans ce document.";
    }

    /**
     * Tente d'extraire le type de document
     */
    private function extractDocumentType(string $text): string
    {
        $types = [
            'Facture' => ['facture', 'invoice', 'reçu', 'quittance'],
            'Contrat' => ['contrat', 'accord', 'bail', 'convention'],
            'Certificat' => ['certificat', 'attestation', 'diplôme'],
            'Rapport' => ['rapport', 'compte-rendu', 'bilan', 'audit'],
            'Identité' => ['carte d\'identité', 'passeport', 'permis']
        ];

        foreach ($types as $label => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) {
                    return "Ce document semble être de type **" . $label . "**. Plusieurs termes techniques s'y rapportant ont été détectés.";
                }
            }
        }

        return "Ce document semble être une note ou une correspondance administrative générale.";
    }

    /**
     * Trouve une réponse contextuelle générique en cherchant la phrase la plus pertinente
     */
    private function findContextualAnswer(string $text, string $question): string
    {
        // On divise en phrases
        $sentences = preg_split('/[.?!]\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $bestSentence = "";
        $maxScore = 0;

        $questionWords = explode(' ', $question);
        $questionWords = array_filter($questionWords, function($w) { return strlen($w) > 3; });

        foreach ($sentences as $sentence) {
            $score = 0;
            foreach ($questionWords as $word) {
                if (stripos($sentence, $word) !== false) {
                    $score++;
                }
            }

            if ($score > $maxScore) {
                $maxScore = $score;
                $bestSentence = $sentence;
            }
        }

        if ($maxScore > 0) {
            return "D'après le passage le plus pertinent : \"*" . trim($bestSentence) . "*\". J'espère que cela répond à votre question.";
        }

        return "Je n'ai pas trouvé de réponse précise à votre question dans ce document spécifique. Pouvez-vous reformuler ?";
    }
}
