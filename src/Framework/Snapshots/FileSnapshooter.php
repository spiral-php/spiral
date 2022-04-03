<?php

declare(strict_types=1);

namespace Spiral\Snapshots;

use Psr\Log\LoggerInterface;
use Spiral\Exceptions\HandlerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class FileSnapshooter implements SnapshotterInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly int $maxFiles,
        private readonly int $verbosity,
        private readonly HandlerInterface $handler,
        private readonly FilesInterface $files,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function register(\Throwable $e): SnapshotInterface
    {
        $snapshot = new Snapshot($this->getID($e), $e);

        if ($this->logger !== null) {
            $this->logger->error($snapshot->getMessage());
        }

        $this->saveSnapshot($snapshot);
        $this->rotateSnapshots();

        return $snapshot;
    }

    protected function saveSnapshot(SnapshotInterface $snapshot): void
    {
        $filename = $this->getFilename($snapshot, new \DateTime());

        $this->files->write(
            $filename,
            $this->handler->renderException($snapshot->getException(), $this->verbosity),
            FilesInterface::RUNTIME,
            true
        );
    }

    /**
     * Remove older snapshots.
     */
    protected function rotateSnapshots(): void
    {
        $finder = new Finder();
        $finder->in($this->directory)->sort(
            static fn (SplFileInfo $a, SplFileInfo $b) => $b->getMTime() - $a->getMTime()
        );

        $count = 0;
        foreach ($finder as $file) {
            $count++;
            if ($count > $this->maxFiles) {
                try {
                    $this->files->delete($file->getRealPath());
                } catch (FilesException) {
                    // ignore
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function getFilename(SnapshotInterface $snapshot, \DateTimeInterface $time): string
    {
        return \sprintf(
            '%s/%s-%s.txt',
            $this->directory,
            $time->format('d.m.Y-Hi.s'),
            (new \ReflectionClass($snapshot->getException()))->getShortName()
        );
    }

    protected function getID(\Throwable $e): string
    {
        return \md5(\implode('|', [$e->getMessage(), $e->getFile(), $e->getLine()]));
    }
}
