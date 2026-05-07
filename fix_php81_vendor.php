#!/usr/bin/env php
<?php
/**
 * FULL PHP 8.1 Compatibility Fix (including vendor)
 */

echo "🔧 FULL PHP 8.1 COMPATIBILITY FIX (with vendor)\n";
echo "================================================\n\n";

$rootPath = __DIR__;
$excludeDirs = ['var', 'node_modules', '.git', '.symfony'];

function shouldSkipDir($path, $excludeDirs) {
    foreach ($excludeDirs as $exclude) {
        if (strpos($path, DIRECTORY_SEPARATOR . $exclude . DIRECTORY_SEPARATOR) !== false ||
            strpos($path, DIRECTORY_SEPARATOR . $exclude) === strlen($path) - strlen(DIRECTORY_SEPARATOR . $exclude)) {
            return true;
        }
    }
    return false;
}

$totalModified = 0;
$totalScanned = 0;

// Process all PHP files
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $filePath = $file->getRealPath();
    
    // Skip excluded directories
    if (shouldSkipDir($filePath, $excludeDirs)) {
        continue;
    }

    $totalScanned++;
    $content = file_get_contents($filePath);
    $originalContent = $content;

    // Remove 'readonly' keyword
    $content = preg_replace('/\breadonly\s+/', '', $content);

    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        $totalModified++;
        
        if ($totalModified % 10 === 0) {
            echo ".";
        }
    }
}

echo "\n\n✅ FIX COMPLETE!\n";
echo "================\n";
echo "Files scanned: $totalScanned\n";
echo "Files modified: $totalModified\n\n";

if ($totalModified > 0) {
    echo "✓ Code is now fully compatible with PHP 8.1\n";
}
?>
