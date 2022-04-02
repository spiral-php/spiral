<?php

declare(strict_types=1);

namespace Spiral\Bootloader\Http;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Http\ErrorHandler;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;

/**
 * Enable support for HTTP error pages.
 */
final class ErrorHandlerBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        HttpBootloader::class,
    ];

    protected const BINDINGS = [
        ErrorHandlerMiddleware\SuppressErrorsInterface::class => ErrorHandlerMiddleware\EnvSuppressErrors::class,
        ErrorHandler\RendererInterface::class                 => ErrorHandler\PlainRenderer::class,
    ];

    public function boot(HttpBootloader $http): void
    {
        $http->addMiddleware(ErrorHandlerMiddleware::class);
    }
}
