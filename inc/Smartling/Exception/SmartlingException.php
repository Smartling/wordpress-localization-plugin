<?php

namespace Smartling\Exception;

use Smartling\Bootstrap;

/**
 * Class SmartlingException
 *
 * @package Smartling\Exception
 *
 * Base class for any Smartling Exception
 */
abstract class SmartlingException extends \Exception
{

    /**
     * @param string     $message
     * @param int        $code
     * @param \Exception $previous
     */
    public function __construct ($message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct ($this, $code, $previous);

        $this->tryLogException ($this);
    }


    /**
     * @param SmartlingException $exception
     *
     * @throws \Exception
     */
    private function tryLogException (SmartlingException $exception)
    {
        try {
            $message = $exception->getMessage ();

            $fileString = vsprintf ("%s : %d", array ($exception->getFile (), $exception->getLine ()));

            $trace = $exception->getTraceAsString ();

            Bootstrap::getLogger ()->error ($message, array ($fileString, $trace));

        } catch (\Exception $e) {
            throw $e;
        }
    }
}
