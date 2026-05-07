<?php

namespace App\Service\Transfer;

final class ScheduledTransferEngineConfigManager
{
    public function __construct(private string $projectDir)
    {
    }

    public function setEngine(string $engine): void
    {
        $engine = strtolower(trim($engine));

        if (!in_array($engine, [ScheduledTransferEngineResolver::ENGINE_SYMFONY, ScheduledTransferEngineResolver::ENGINE_N8N], true)) {
            throw new \InvalidArgumentException('Engine must be "symfony" or "n8n".');
        }

        $envLocalPath = $this->projectDir . DIRECTORY_SEPARATOR . '.env.local';
        $content = is_file($envLocalPath) ? (string) file_get_contents($envLocalPath) : '';

        $line = 'SCHEDULED_TRANSFER_ENGINE=' . $engine;

        if (preg_match('/^SCHEDULED_TRANSFER_ENGINE=.*/m', $content) === 1) {
            $content = (string) preg_replace('/^SCHEDULED_TRANSFER_ENGINE=.*/m', $line, $content);
        } else {
            $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
        }

        file_put_contents($envLocalPath, $content);
    }
}
