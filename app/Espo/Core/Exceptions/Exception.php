<?php
declare(strict_types=1);

namespace Espo\Core\Exceptions;

/**
 * Class Exception
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Exception extends \Exception
{
    /**
     * @inheritDoc
     */
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        // decode message to utf8
        $message = utf8_decode($message);

        parent::__construct($message, $code, $previous);
    }
}
