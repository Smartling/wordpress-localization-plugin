<?php
$directory = __DIR__ . '/inc/lib';
$pattern = '/Smartling\\\\Vendor\\\\Smartling\\\\Vendor\\\\/';
$replacement = 'Smartling\\Vendor\\';
$pass = 1;
$columns = 120;

do {
    echo "Scanning directory: $directory, pass $pass\n";
    echo str_repeat('-', $columns) . "\n";
    $filesFound = 0;
    $filesModified = 0;
    $totalReplacements = 0;
    foreach (new RecursiveIteratorIterator(
                 new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                 RecursiveIteratorIterator::SELF_FIRST,
             ) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filesFound++;
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);

            $count = preg_match_all($pattern, $content);

            if ($count > 0) {
                file_put_contents($filePath, preg_replace($pattern, $replacement, $content));

                $filesModified++;
                $totalReplacements += $count;

                $relativePath = str_replace(__DIR__ . '/', '', $filePath);
                echo "✓ Modified: $relativePath ($count replacements)\n";
            }
        }
    }

    echo str_repeat('-', $columns) . "\n";
    echo "Summary:\n";
    echo "  Files found: $filesFound\n";
    echo "  Files modified: $filesModified\n";
    echo "  Total replacements: $totalReplacements\n";
    echo "\nDone!\n";
    ++$pass;
} while ($filesModified > 0);

// Copy classmap files that are present in inc/third-party but absent from inc/lib.
// The namespacer skips namespace-less files (e.g. ValueWrapper.php), leaving them missing
// in inc/lib even though the generated autoloader still expects them.
$libDir = __DIR__ . '/inc/lib';
$thirdPartyDir = __DIR__ . '/inc/third-party';
$prefix = 'smartling-connector-';

echo "\n" . str_repeat('=', $columns) . "\n";
echo "Checking for classmap files missing from inc/lib...\n";
echo str_repeat('=', $columns) . "\n";

$filesCopied = 0;
$filesMissing = 0;

foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($libDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
) as $file) {
    if (!$file->isFile() || $file->getFilename() !== 'composer.json') {
        continue;
    }

    $packageDir = $file->getPath();
    try {
        $composer = json_decode(file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        echo "✗ Failed to parse: " . str_replace(__DIR__ . '/', '', $file->getPathname()) . " ({$e->getMessage()})\n";
        continue;
    }
    $classmap = $composer['autoload']['classmap'] ?? [];

    if (empty($classmap)) {
        continue;
    }

    // Derive the original vendor/package path from the scoped name.
    // e.g. "smartling-connector-symfony/cache" -> "symfony/cache"
    $relativePackageDir = ltrim(str_replace($libDir, '', $packageDir), '/');
    $parts = explode('/', $relativePackageDir, 2);
    if (count($parts) !== 2 || !str_starts_with($parts[0], $prefix)) {
        continue;
    }
    $originalVendor = substr($parts[0], strlen($prefix));
    $originalPackageDir = $thirdPartyDir . '/' . $originalVendor . '/' . $parts[1];

    foreach ($classmap as $classmapEntry) {
        $libFile = $packageDir . '/' . ltrim($classmapEntry, '/');
        if (file_exists($libFile)) {
            continue;
        }

        $filesMissing++;
        $sourceFile = $originalPackageDir . '/' . ltrim($classmapEntry, '/');
        $relativeLibPath = str_replace(__DIR__ . '/', '', $libFile);

        if (!file_exists($sourceFile)) {
            echo "✗ Missing in both lib and third-party: $relativeLibPath\n";
            continue;
        }

        $dir = dirname($libFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            echo "✗ Failed to create directory: $dir\n";
            continue;
        }
        copy($sourceFile, $libFile);
        $filesCopied++;
        echo "✓ Copied: $relativeLibPath\n";
    }
}

echo str_repeat('-', $columns) . "\n";
echo "Summary:\n";
echo "  Missing classmap files found: $filesMissing\n";
echo "  Files copied: $filesCopied\n";
echo "\nDone!\n";
