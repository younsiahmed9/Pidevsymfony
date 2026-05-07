#!/usr/bin/env php
<?php
/**
 * Comprehensive PHP 8.1 Compatibility Fix
 * Removes 'readonly' keyword from all PHP files
 */

echo "🔧 COMPREHENSIVE PHP 8.1 FIX\n";
echo "=============================\n\n";

$basePaths = [
    __DIR__ . '/src',
    __DIR__ . '/config',
    __DIR__ . '/public',
];

$totalModified = 0;

foreach ($basePaths as $basePath) {
    if (!is_dir($basePath)) {
        echo "⚠️  Skipping (not found): $basePath\n";
        continue;
    }

    echo "\n📂 Scanning: $basePath\n";
    echo "-" . str_repeat("-", strlen($basePath)) . "\n";
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $pathCount = 0;
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        // Remove 'readonly' keyword
        $content = preg_replace('/\breadonly\s+/', '', $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            $totalModified++;
            $pathCount++;
        }
    }
    
    echo "✓ Modified: $pathCount files\n";
}

echo "\n✅ FIX COMPLETE!\n";
echo "================\n";
echo "Total files modified: $totalModified\n";

if ($totalModified > 0) {
    echo "✓ Code is now compatible with PHP 8.1\n\n";
} else {
    echo "ℹ️  No changes needed\n\n";
}
?>
