<?php

namespace Smartling\WP\Controller;


use Smartling\Exception\SmartlingIOException;

abstract class ControllerAbstract
{
    protected function renderScript(?string $script = null): void
    {
        if (null === $script) {
            $parts = explode('\\', get_called_class());
            $script = vsprintf(
                '%s/%s.php',
                [
                    str_replace('Controller', 'View', __DIR__),
                    end($parts),
                ]
            );
        }

        if (!file_exists($script) || !is_file($script) || !is_readable($script)) {
            throw new SmartlingIOException(vsprintf('Requested view file (%s) not found.', [$script]));
        }

        require_once $script;
    }

    private array $viewData;

    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function setViewData(array $viewData): void
    {
        $this->viewData = $viewData;
    }

    public function getUploadedFileContents(string $inputName): string
    {
        if (!isset($_FILES) || !array_key_exists($inputName, $_FILES)) {
            throw new \RuntimeException('File not uploaded');
        }
        $result = file_get_contents($_FILES[$inputName]['tmp_name']);
        if (!is_string($result)) {
            throw new \RuntimeException('Unable to get contents from ' . $_FILES[$inputName]['tmp_name']);
        }

        return $result;
    }
}
