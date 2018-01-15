<?php

namespace Smartling\Extensions;

use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class ExtensionLoader
 *
 * @package Smartling\Extensions
 */
class ExtensionLoader extends SmartlingFactoryAbstract
{

    public function registerExtension(ExtensionInterface $extension)
    {
        $this->registerHandler($extension->getName(), $extension);
    }

    public function runExtensions()
    {
        $extenstions = $this->getCollection();
        if (0 < count($extenstions)) {
            foreach ($extenstions as $name => $extension) {
                try {
                    /**
                     * @var ExtensionInterface $extension
                     */
                    $extension->register();
                } catch (\Exception $e) {
                    $this->getLogger()
                         ->error('Failed initialization of ' . $name . ' extension.');
                }
            }
        }
    }
}