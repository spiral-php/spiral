<?php

declare(strict_types=1);

namespace Spiral\Stempler\Node;

use Spiral\Stempler\Node\Traits\AttributeTrait;
use Spiral\Stempler\Node\Traits\ContextTrait;
use Spiral\Stempler\Parser\Context;

/**
 * Top level template node.
 */
final class Template implements NodeInterface, AttributedInterface
{
    use AttributeTrait;
    use ContextTrait;

    /**
     * @param NodeInterface[] $nodes
     */
    public function __construct(
        public array $nodes = []
    ) {
    }

    public function setContext(Context $context = null): void
    {
        $this->context = $context;
    }

    public function getContext(): ?Context
    {
        return $this->context;
    }

    public function getIterator(): \Generator
    {
        yield 'nodes' => $this->nodes;
    }
}
