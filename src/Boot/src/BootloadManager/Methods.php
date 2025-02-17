<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

/**
 * @deprecated Will be removed in v4.0
 */
enum Methods: string
{
    case INIT = 'init';
    case BOOT = 'boot';
}
