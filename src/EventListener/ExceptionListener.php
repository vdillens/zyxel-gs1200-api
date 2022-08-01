<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event)
    {
        // You get the exception object from the received event
        $exception = $event->getThrowable();
        // Get incoming request
        $request   = $event->getRequest();

        $code = ($exception instanceof HttpExceptionInterface) ? $exception->getStatusCode() : $exception->getCode();
        // Customize your response object to display the exception details
        $response = new JsonResponse([
            'message'       => $exception->getMessage(),
            'code'          => $code,
            'traces'        => $exception->getTrace()
        ]);

        // HttpExceptionInterface is a special type of exception that
        // holds status code and header details
        if ($exception instanceof HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $response->headers->set('Content-Type', 'application/json');
        // sends the modified response object to the event
        $event->setResponse($response);
    }
}
