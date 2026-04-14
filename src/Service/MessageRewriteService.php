<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MessageRewriteService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function rewrite(string $message): string
    {
        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            throw new \RuntimeException('Missing AI API key for message rewriting.');
        }

        $provider = $this->resolveProvider($apiKey);
        $apiUrl = $this->resolveApiUrl($provider);
        $model = $this->resolveModel($provider);

        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un reformulateur de message. Ta tache est uniquement de reecrire le texte fourni, pas d y repondre. Interdiction de jouer le role d assistant (pas de "je serais ravi de vous aider", pas de questions de clarification, pas de conseils). Tu dois produire une version formelle et au theme du finance , polie et professionnelle en francais, destinee a etre envoyee telle quelle a un interlocuteur. Conserve strictement l intention et les informations du message original, sans ajout. Retourne uniquement la phrase reformulee.',
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 20,
            ]);

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('AI rewrite request failed.', 0, $exception);
        }

        $rewritten = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($rewritten === '') {
            throw new \RuntimeException('AI rewrite returned empty content.');
        }

        return mb_substr($rewritten, 0, 2000);
    }

    private function resolveApiKey(): string
    {
        $candidates = [
            (string) ($_ENV['MESSAGE_REWRITE_API_KEY'] ?? ''),
            (string) ($_SERVER['MESSAGE_REWRITE_API_KEY'] ?? ''),
            (string) ($_ENV['GROQ_API_KEY'] ?? ''),
            (string) ($_SERVER['GROQ_API_KEY'] ?? ''),
            (string) ($_ENV['OPENAI_API_KEY'] ?? ''),
            (string) ($_SERVER['OPENAI_API_KEY'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $value = trim($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveProvider(string $apiKey): string
    {
        if (str_starts_with($apiKey, 'gsk_')) {
            return 'groq';
        }

        return 'openai';
    }

    private function resolveApiUrl(string $provider): string
    {
        $candidates = [
            (string) ($_ENV['MESSAGE_REWRITE_API_URL'] ?? ''),
            (string) ($_SERVER['MESSAGE_REWRITE_API_URL'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $value = trim($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        if ($provider === 'groq') {
            return 'https://api.groq.com/openai/v1/chat/completions';
        }

        return 'https://api.openai.com/v1/chat/completions';
    }

    private function resolveModel(string $provider): string
    {
        $candidates = [
            (string) ($_ENV['MESSAGE_REWRITE_MODEL'] ?? ''),
            (string) ($_SERVER['MESSAGE_REWRITE_MODEL'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $value = trim($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        if ($provider === 'groq') {
            return 'llama-3.1-8b-instant';
        }

        return 'gpt-4o-mini';
    }
}
