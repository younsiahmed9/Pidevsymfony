<?php

namespace App\Service\Transfer;

final class ScheduledTransferEngineResolver
{
    public const ENGINE_SYMFONY = 'symfony';
    public const ENGINE_N8N = 'n8n';

    public function __construct(private string $configuredEngine)
    {
    }

    public function getEngine(): string
    {
        $engine = strtolower(trim($this->configuredEngine));

        return in_array($engine, [self::ENGINE_SYMFONY, self::ENGINE_N8N], true)
            ? $engine
            : self::ENGINE_SYMFONY;
    }

    public function isSymfony(): bool
    {
        return $this->getEngine() === self::ENGINE_SYMFONY;
    }

    public function isN8n(): bool
    {
        return $this->getEngine() === self::ENGINE_N8N;
    }
}
