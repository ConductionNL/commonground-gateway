<?php

namespace App\Exception;

/**
 * Custom exception used in HandlerService. 
 * Examples of usage can be found in HandlerService 
 */
class GatewayException extends \Exception
{

    private $_options;

    public function __construct(
        // Message of error
        $message,
        // Can be null
        $code = 0,
        // Can be null
        \Exception $previous = null,
        // Array with body, path and responseType (201, 404, 502 etc)
        $options = array('params')
    ) {
        parent::__construct($message, $code, $previous);

        $this->_options = $options;
    }

    public function GetOptions()
    {
        return $this->_options;
    }
}
