<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Mailer;

use DateTimeInterface;
use DateInterval;
use DateTimeImmutable;
class Message implements MessageInterface
{
    private ?string $subject = null;

    private ?array $data = null;

    private array $to = [];

    private array $cc = [];

    private array $bcc = [];

    private ?string $from = null;

    private ?string $replyTo = null;

    private array $options = [];

    /**
     * @param string|string[] $to
     */
    public function __construct(string $subject, $to, array $data = [])
    {
        $this->setSubject($subject);
        $this->setTo(...(array)$to);
        $this->setData($data);
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setTo(string ...$to): self
    {
        $this->to = $to;

        return $this;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function setCC(string ...$cc): self
    {
        $this->cc = $cc;

        return $this;
    }

    public function getCC(): array
    {
        return $this->cc;
    }

    public function setBCC(string ...$bcc): self
    {
        $this->bcc = $bcc;

        return $this;
    }

    public function getBCC(): array
    {
        return $this->bcc;
    }

    public function setFrom(?string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setReplyTo(?string $replyTo): self
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function setOption(string $name, $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param int|DateTimeInterface|DateInterval $delay
     */
    public function setDelay($delay): self
    {
        if ($delay instanceof DateInterval) {
            $delay = (new DateTimeImmutable('NOW'))->add($delay);
        }

        if ($delay instanceof DateTimeInterface) {
            $delay = max(0, $delay->getTimestamp() - time());
        }

        return $this->setOption('delay', (int)$delay);
    }
}
