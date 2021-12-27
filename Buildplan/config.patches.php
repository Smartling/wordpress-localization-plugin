<?php

return [
    "prepare" => [
        function(string $package, array $config, string $path, string $namespacePrefix) {
            echo "prepare $package $path $namespacePrefix";

            return $config;
        }
    ],

    "start" => [
        function(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
            echo "start $source $currentNamespace $namespacePrefix $package $file";

            return $source;
        },
    ],
];
