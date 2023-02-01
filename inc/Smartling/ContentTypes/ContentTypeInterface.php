<?php

namespace Smartling\ContentTypes;

use JetBrains\PhpStorm\ExpectedValues;

interface ContentTypeInterface
{
    /**
     * WordPress slug of content-type, e.g.: post, page, post-tag
     */
    public function getSystemName(): string;

    /**
     * Display name of content type, e.g.: Post
     */
    public function getLabel(): string;

    public function isVisible(string $page): bool;

    public function isTaxonomy(): bool;

    public function isPost(): bool;

    public function isVirtual(): bool;

    /**
     * Display in filters even if not registered in WordPress
     */
    public function forceDisplay(): bool;

    /**
     * @return string
     */
    #[ExpectedValues(values: ['post', 'taxonomy'])]
    public function getBaseType(): string;
}
