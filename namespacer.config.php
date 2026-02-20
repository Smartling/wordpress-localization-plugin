<?php

return [
    "prepare" => [
        function(string $package, array $config, string $path, string $namespacePrefix) {
            // ValueWrapper.php defines a class named with the Unicode replacement character (U+FFFD).
            // ClassMapGenerator in Docker cannot scan it via is_file(), so move it from classmap to
            // files (always-required) so it is still loaded but never scanned for class names.
            if ($package === 'symfony/cache' && isset($config['autoload']['classmap'])) {
                $entry = 'Traits/ValueWrapper.php';
                $key = array_search($entry, $config['autoload']['classmap']);
                if ($key !== false) {
                    unset($config['autoload']['classmap'][$key]);
                    $config['autoload']['classmap'] = array_values($config['autoload']['classmap']);
                    $config['autoload']['files'][] = $entry;
                }
            }

            return $config;
        }
    ],
];
