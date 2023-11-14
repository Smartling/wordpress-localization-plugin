<?php

namespace Smartling\Processors;

use Smartling\DbAl\WordpressContentEntities\EntityHandler;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;

class ContentEntitiesIOFactory extends SmartlingFactoryAbstract
{
    public function __construct(bool $allowDefault = false, ?object $defaultHandler = null)
    {
        parent::__construct($allowDefault, $defaultHandler);
        $this->message = 'Requested entity wrapper for content-type \'%s\' is not registered. Called by: %s';
    }

    /**
     * @throws SmartlingConfigException
     * @throws SmartlingInvalidFactoryArgumentException
     */
    public function getMapper(string $contentType): EntityHandler
    {
        $obj = $this->getHandler($contentType);

        if ($obj instanceof EntityHandler) {
            return clone $obj;
        }

        throw new SmartlingConfigException(self::class . __METHOD__ . ' expected return is ' . EntityHandler::class);
    }
}
