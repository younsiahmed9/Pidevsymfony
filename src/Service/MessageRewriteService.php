<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MessageRewriteService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $groqApiKey = '',
    ) {
    }

    public function rewrite(string $message): string
    {
        return $this->fallbackRewrite($message);
    }

    private function resolveApiKey(): string
    {
        $candidates = [
            $this->groqApiKey,
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

    private function fallbackRewrite(string $message): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        if ($normalized === '') {
            return '';
        }

        $normalized = str_ireplace(
            ['salut', 'cc', 'coucou', 'stp', 'svp', 'merci beaucoup', 'merci'],
            ['Bonjour', 'Bonjour', 'Bonjour', 's’il vous plaît', 's’il vous plaît', 'merci beaucoup', 'merci'],
            $normalized
        );

        $normalized = preg_replace('/\btu\b/iu', 'vous', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bton\b/iu', 'votre', $normalized) ?? $normalized;
        $normalized = preg_replace('/\btes\b/iu', 'vos', $normalized) ?? $normalized;
        $normalized = preg_replace('/\btoi\b/iu', 'vous', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bstp\b/iu', 's’il vous plaît', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bsvp\b/iu', 's’il vous plaît', $normalized) ?? $normalized;

        if (!preg_match('/^(bonjour|bonsoir|salutations)\b/iu', $normalized)) {
            $normalized = 'Bonjour, ' . ltrim($normalized);
        }

        $normalized = mb_strtolower($normalized);
        $normalized = preg_replace_callback('/(^|[\.\!\?]\s+)(\p{L})/u', static function (array $matches): string {
            return $matches[1] . mb_strtoupper($matches[2]);
        }, $normalized) ?? $normalized;

        $normalized = preg_replace('/\s+,/u', ',', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+([\.\!\?])/u', '$1', $normalized) ?? $normalized;

        if (!preg_match('/[\.\!\?]$/u', $normalized)) {
            $normalized .= '.';
        }

        return mb_substr($normalized, 0, 2000);
    }

    private function looksRewritten(string $original, string $rewritten): bool
    {
        $originalNormalized = $this->normalizeForComparison($original);
        $rewrittenNormalized = $this->normalizeForComparison($rewritten);

        return $originalNormalized !== '' && $rewrittenNormalized !== '' && $originalNormalized !== $rewrittenNormalized;
    }

    private function normalizeForComparison(string $message): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $message) ?? $message));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? $normalized;

        return $normalized;
    }

    private function looksLikeAssistantReply(string $message): bool
    {
        $patterns = [
            '/\bje\s+(?:serais|suis|peux|pourrais|vais)\b/iu',
            '/\bje\s+suis\s+ravi\b/iu',
            '/\bje\s+serais\s+ravi\b/iu',
            '/\bje\s+peux\s+vous\s+aider\b/iu',
            '/\bcomment\s+puis[-\s]je\b/iu',
            '/\bpouvez[-\s]vous\b/iu',
            '/\bveuillez\b/iu',
            '/\bn[’\'`]hesitez\s+pas\b/iu',
            '/\bacceder\b/iu',
            '/\bprocessus\b/iu',
            '/\bguide\b/iu',
            '/\bconsulter\b.*\bcompte\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }
}
