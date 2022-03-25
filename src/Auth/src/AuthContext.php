<?php

declare(strict_types=1);

namespace Spiral\Auth;

final class AuthContext implements AuthContextInterface
{
    private ?TokenInterface $token = null;
    private ?object $actor = null;
    private ?string $transport = null;
    private bool $closed = false;

    public function __construct(
        private readonly ActorProviderInterface $actorProvider
    ) {
    }

    public function start(TokenInterface $token, string $transport = null): void
    {
        $this->closed = false;
        $this->actor = null;
        $this->token = $token;
        $this->transport = $transport;
    }

    public function getToken(): ?TokenInterface
    {
        return $this->token;
    }

    public function getTransport(): ?string
    {
        return $this->transport;
    }

    public function getActor(): ?object
    {
        if ($this->closed) {
            return null;
        }

        if ($this->actor === null && $this->token !== null) {
            $this->actor = $this->actorProvider->getActor($this->token);
        }

        return $this->actor;
    }

    public function close(): void
    {
        $this->closed = true;
        $this->actor = null;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
