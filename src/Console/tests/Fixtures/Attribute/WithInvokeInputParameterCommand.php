<?php

namespace Spiral\Tests\Console\Fixtures\Attribute;

use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\AsInput;
use Spiral\Console\Command;
use Spiral\Tests\Console\Fixtures\Attribute\Input\InputSource;

#[AsCommand(name: 'attribute-with-description', description: 'Some description text')]
final class WithInvokeInputParameterCommand extends Command
{
    public function __invoke(#[AsInput] InputSource $input): int
    {
        $this->write($this->getDescription());

        return self::SUCCESS;
    }
}
