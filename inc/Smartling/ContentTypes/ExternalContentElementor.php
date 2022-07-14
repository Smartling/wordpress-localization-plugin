<?php

namespace Smartling\ContentTypes;

use Smartling\Submissions\SubmissionEntity;

class ExternalContentElementor implements ContentTypeModifyingInterface
{
    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        return json_decode($source['meta']['_elementor_data'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    }

    public function getMaxVersion(): string
    {
        return '3.6';
    }

    public function getMinVersion(): string
    {
        return '3.4';
    }

    public function getPluginId(): string
    {
        return 'elementor';
    }

    public function getPluginPath(): string
    {
        return 'elementor/elementor.php';
    }

    public function setContentFields(array $content, SubmissionEntity $submission): array
    {
        $content['meta']['_elementor_data'] = json_encode($content['elementor'], JSON_THROW_ON_ERROR);
        return $content;
    }

    public function alterContentFields(array $source): array
    {
        unset($source['meta']['_elementor_data']);
        return $source;
    }
}
