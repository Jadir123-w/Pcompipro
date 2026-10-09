<?php

namespace Modules\Marketing\Exceptions;

use Exception;

/**
 * AIProviderException
 *
 * Excepción lanzada cuando el proveedor de IA falla.
 */
class AIProviderException extends Exception
{
    /** @var string */
    protected $provider;

    /** @var array|null */
    protected $responseBody;

    /**
     * @param  string          $message
     * @param  string          $provider
     * @param  array|null      $responseBody
     * @param  int             $code
     * @param  \Throwable|null $previous
     */
    public function __construct(
        $message,
        $provider = 'unknown',
        $responseBody = null,
        $code = 0,
        $previous = null
    ) {
        $this->provider = $provider;
        $this->responseBody = $responseBody;

        parent::__construct("[AI:{$provider}] {$message}", $code, $previous);
    }

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @return array|null
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }
}
