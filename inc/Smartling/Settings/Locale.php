<?php
namespace Smartling\Settings;

class Locale
{
    private int $blogId;
    private string $label;

    public function getBlogId(): int
    {
        return $this->blogId;
    }

    public function setBlogId(int $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}
