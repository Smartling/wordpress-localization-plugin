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
