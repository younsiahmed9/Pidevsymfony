<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ContentModerationService
{
    private const DEFAULT_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'llama-3.1-8b-instant';
    private const FLAG_THRESHOLD = 0.25;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $groqApiKey,
        private string $apiUrl = self::DEFAULT_API_URL,
    ) {
    }

    /**
     * @return array{flagged: bool, source: string, reason: string, categories: string[]}
     */
    public function assess(string $message): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        if ($text === '') {
            return [
                'flagged' => false,
                'source' => 'none',
                'reason' => '',
                'categories' => [],
            ];
        }

        try {
            return $this->detectWithJudgeApi($text, $this->apiKey, self::DEFAULT_MODEL, $this->apiUrl);
        } catch (\Throwable $primaryException) {
            $fallbackKey = trim($this->groqApiKey);
            if ($fallbackKey === '') {
                throw $primaryException;
            }

            return $this->detectWithJudgeApi($text, $fallbackKey, self::GROQ_MODEL, self::GROQ_API_URL);
        }
    }

    /**
     * @return array{flagged: bool, reason: string, categories: string[]}
     */
    private function detectWithJudgeApi(string $message, string $apiKey, string $model, string $apiUrl): array
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new \RuntimeException('Missing moderation API key.');
        }

        $payload = [
            'model' => $model,
            'temperature' => 0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un moderateur de contenu tres strict pour une messagerie. Analyse le message utilisateur et retourne uniquement un JSON valide avec les cles: flagged (bool), score (nombre entre 0 et 1), reason (string), categories (tableau de strings). Flagged doit etre true des qu il y a impolitesse, agressivite, insulte, attaque personnelle, haine generale, vulgarite, humiliation, harcelement, mepris ou ton degrade. Considere comme exemple a signaler: "you are terrible", "i hate everyone so much", "you suck", "stupid", "shut up". Si le message contient une intention negative meme legere, le score doit etre au moins 0.25. Retourne uniquement le JSON, sans texte autour.',
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
        ];

        if ($apiUrl === self::DEFAULT_API_URL) {
            $payload['response_format'] = [
                'type' => 'json_object',
            ];
        }

        try {
            $response = $this->httpClient->request('POST', $apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 15,
            ]);

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Moderation API request failed.', 0, $exception);
        }

        $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            throw new \RuntimeException('Moderation API returned empty content.');
        }

        $content = preg_replace('/^```json\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Moderation API returned invalid JSON.');
        }

        $flagged = (bool) ($decoded['flagged'] ?? false);
        $score = (float) ($decoded['score'] ?? 0.0);
        $reason = trim((string) ($decoded['reason'] ?? ''));
        $categories = array_values(array_filter(array_map('strval', (array) ($decoded['categories'] ?? []))));

        if (!$flagged && $score >= self::FLAG_THRESHOLD) {
            $flagged = true;
        }

        if ($flagged && $reason === '') {
            $reason = 'Le message a ete signale par la moderation automatique.';
        }

        if (!$flagged) {
            return [
                'flagged' => false,
                'reason' => '',
                'categories' => [],
            ];
        }

        return [
            'flagged' => true,
            'reason' => $reason,
            'categories' => $categories,
        ];
    }
}