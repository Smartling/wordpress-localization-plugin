<?php

namespace Smartling\WP\View;

use JetBrains\PhpStorm\ArrayShape;

class BulkSubmitScreenRow {
    private mixed $id;
    private string $title;
    private string $type;
    private ?string $author;
    private ?string $locales;
    private ?string $status;
    private ?string $updated;

    public function __construct(mixed $id, string $title, string $type, ?string $author = null, ?string $locales = null, ?string $status = null, ?string $updated = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->type = $type;
        $this->author = $author;
        $this->locales = $locales;
        $this->status = $status;
        $this->updated = $updated;
    }

    #[ArrayShape([
        'author' => 'string',
        'id' => 'mixed',
        'status' => 'string',
        'title' => 'string',
        'locales' => 'string',
        'type' => 'string',
        'updated' => 'string',
    ])]
    public function toArray(): array
    {
        return [
            'author' => $this->author,
            'id' => $this->id,
            'status' => $this->status,
            'title' => $this->title,
            'locales' => $this->locales,
            'type' => $this->type,
            'updated' => $this->updated,
        ];
    }
}
