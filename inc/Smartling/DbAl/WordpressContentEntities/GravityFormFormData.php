<?php

namespace Smartling\DbAl\WordpressContentEntities;

class GravityFormFormData {
    private string $display_meta;
    private string $title;
    private string $updated;

    public function __construct(string $display_meta, string $title, string $updated)
    {
        $this->display_meta = $display_meta;
        $this->title = $title;
        $this->updated = $updated;
    }

    public function getDisplayMeta(): string
    {
        return $this->display_meta;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUpdated(): string
    {
        return $this->updated;
    }
}
