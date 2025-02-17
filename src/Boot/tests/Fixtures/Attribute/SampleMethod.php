<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\Fixtures\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class SampleMethod extends \Spiral\Boot\Attribute\AbstractMethod {}
