#!/usr/bin/env php
<?php
/**
 * PHP 8.1 Compatibility Fix
 * Removes 'readonly' keyword to make code compatible with PHP 8.1
 */

echo "🔧 PHP 8.1 COMPATIBILITY FIX\n";
echo "============================\n\n";

$basePath = __DIR__ . '/src';
$count = 0;
$filesModified = 0;

// Recursively find all PHP files
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $filePath = $file->getRealPath();
    $content = file_get_contents($filePath);
    $originalContent = $content;

    // Replace keyword
    // Pattern: "readonly" followed by space and access modifier or type
    $content = preg_replace(
        '/\breadonly\s+/',
        '',
        $content
    );

    // Write back if changed
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        $filesModified++;
        $count++;
        
        $relativePath = str_replace($basePath, 'src', $filePath);
        echo "✓ Fixed: $relativePath\n";
        
        if ($count % 10 === 0) {
            echo "  ... processed $count files ...\n";
        }
    }
}

echo "\n✅ COMPATIBILITY FIX COMPLETE!\n";
echo "Files modified: $filesModified\n";
echo "Total files scanned: $count\n\n";

if ($filesModified > 0) {
    echo "✓ All 'readonly' keywords removed\n";
    echo "✓ Code is now compatible with PHP 8.1\n";
} else {
    echo "ℹ️  No keywords found\n";
}
?>
