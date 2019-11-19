<?php

namespace Smartling\WP\Controller;


use Smartling\Exception\SmartlingIOException;

abstract class ControllerAbstract
{
    /**
     * @param null|string $script
     *
     * @return void
     */
    protected function renderScript($script = null)
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
        } else {
            /** @noinspection PhpIncludeInspection */
            require_once $script;
        }
    }

    /**
     * @var array
     */
    private $viewData;

    /**
     * @return mixed
     */
    public function getViewData()
    {
        return $this->viewData;
    }

    /**
     * @param mixed $viewData
     */
    public function setViewData($viewData)
    {
        $this->viewData = $viewData;
    }
}
