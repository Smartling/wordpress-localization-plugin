<?php

namespace Smartling\Extensions;

use Smartling\Exception\SmartlingConfigException;
use Smartling\Processors\SmartlingFactoryAbstract;

class ExtensionLoader extends SmartlingFactoryAbstract
{
    public function registerExtension(ExtensionInterface $extension): void
    {
        $this->registerHandler($extension->getName(), $extension);
    }

    /** @noinspection PhpUnused used in Bootstrap */
    public function runExtensions(): void
    {
        foreach ($this->collection as $name => $extension) {
            try {
                if (!$extension instanceof ExtensionInterface) {
                    throw new SmartlingConfigException(self::class . ' expects a collection of ' . ExtensionInterface::class);
                }
                $extension->register();
            } catch (\Exception $e) {
                $this->getLogger()->error("Failed initialization of $name extension: {$e->getMessage()}");
            }
        }
    }
}
