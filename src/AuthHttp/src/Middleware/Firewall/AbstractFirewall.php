<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Auth\Middleware\Firewall;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Auth\AuthContextInterface;
use Spiral\Auth\Middleware\AuthMiddleware;

/**
 * Apply deny filter if user is not authenticated.
 */
abstract class AbstractFirewall implements MiddlewareInterface
{
    /**
     * @param Request                 $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        /** @var AuthContextInterface $context */
        $authContext = $request->getAttribute(AuthMiddleware::ATTRIBUTE);

        if ($authContext === null || $authContext->getActor() === null) {
            return $this->denyAccess($request, $handler);
        }

        return $this->grantAccess($request, $handler);
    }

    /**
     * @param Request                 $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    abstract protected function denyAccess(Request $request, RequestHandlerInterface $handler): Response;

    /**
     * @param Request                 $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    protected function grantAccess(Request $request, RequestHandlerInterface $handler): Response
    {
        return $handler->handle($request);
    }
}
