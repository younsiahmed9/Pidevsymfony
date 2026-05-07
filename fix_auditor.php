#!/usr/bin/env php
<?php
/**
 * Fix PHP 8.1 compatibility in damienharper/auditor vendor
 */

echo "🔧 FIXING PHP 8.1 COMPATIBILITY (damienharper/auditor)\n";
echo "=====================================================\n\n";

$vendorPath = __DIR__ . '/vendor/damienharper';

if (!is_dir($vendorPath)) {
    echo "❌ Vendor path not found: $vendorPath\n";
    exit(1);
}

$modified = 0;
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($vendorPath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $filePath = $file->getRealPath();
    $content = file_get_contents($filePath);
    $original = $content;

    // Remove 'readonly' keyword (handles class definitions and constructor parameters)
    // Pattern 1: readonly class SomeName
    $content = preg_replace('/\b(final\s+)?readonly\s+class\b/', '$1class', $content);
    
    // Pattern 2: constructor parameters like: private readonly Type $var
    $content = preg_replace('/\b(private|public|protected)\s+readonly\s+/', '$1 ', $content);
    
    // Pattern 3: property definitions: private readonly Type $var
    $content = preg_replace('/^\s*(private|public|protected)\s+readonly\s+/', '$1 ', $content, -1, $count);
    if ($count === 0) {
        $content = preg_replace('/(:\s*)(private|public|protected)\s+readonly\s+/', '$1$2 ', $content);
    }

    // Pattern 4: standalone readonly properties
    $content = preg_replace('/\breadonly\s+/', '', $content);

    if ($content !== $original) {
        file_put_contents($filePath, $content);
        $modified++;
        
        echo "✓ " . basename($filePath) . "\n";
    }
}

echo "\n✅ COMPLETE!\n";
echo "Files modified: $modified\n";
?>
