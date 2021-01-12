<?php
namespace PhpRest\Exception;

use Symfony\Component\HttpFoundation\Response;

interface IExceptionHandler
{
    /**
     * @param \Throwable $e
     * @return Response
     */
    public function render(\Throwable $e);
}