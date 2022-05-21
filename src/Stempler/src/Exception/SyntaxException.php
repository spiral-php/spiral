<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Stempler\Exception;

use RuntimeException;
use Spiral\Stempler\Lexer\Token;

/**
 * Syntax exceptions can be intercepted at Builder level to properly associate
 * filepath.
 */
class SyntaxException extends RuntimeException
{
    private Token $token;

    public function __construct(string $message, Token $context)
    {
        $this->token = $context;

        $message = sprintf('%s at offset %s', $message, $context->offset);
        parent::__construct($message, 0, null);
    }

    public function getToken(): Token
    {
        return $this->token;
    }
}
