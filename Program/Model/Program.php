<?php
declare(strict_types=1);

namespace TVGuide\Program\Model;

use DateTimeImmutable;
use TVGuide\Program\Contract\Program as ProgramInterface;

use function _;
use function round;
use function sprintf;
use function time;

final readonly class Program implements ProgramInterface
{
    private string $description;

    public function __construct(
        private int $id,
        private string $title,
        string $description,
        private DateTimeImmutable $startTime,
        private DateTimeImmutable $endTime,
        private int $season = 0,
        private int $episode = 0,
        private int $channelId = 0,
    ) {
        if ($description === '') {
            $this->description = _('No description');
        } else {
            $this->description = $description;
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function channelId(): int
    {
        return $this->channelId;
    }

    public function description(): string
    {
        $description = '';

        if ($this->season() !== 0 || $this->episode() !== 0) {
            $description = '(';
            if ($this->season() !== 0) {
                $description .= sprintf(_('Season: %d'), $this->season());
            }
            if ($this->season() !== 0 && $this->episode() !== 0) {
                $description .= ', ';
            }
            if ($this->episode() !== 0) {
                $description .= sprintf(_('Episode: %d'), $this->episode());
            }
            $description .= ') ';
        }

        return $description . $this->description;
    }

    public function startTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function endTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    public function season(): int
    {
        return $this->season;
    }

    public function episode(): int
    {
        return $this->episode;
    }

    public function isRunning(): bool
    {
        return $this->startTime()->getTimestamp() < time() &&
            $this->endTime()->getTimestamp() > time();
    }

    public function hasEnded(): bool
    {
        return $this->endTime()->getTimestamp() < time();
    }

    public function runtimeSeconds(): int
    {
        return $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
    }

    public function runtimeElapsedPercentage(): int
    {
        if (!$this->isRunning()) {
            return 0;
        }

        if ($this->hasEnded()) {
            return 100;
        }

        $runtimeElapsedSeconds = time() - $this->startTime->getTimestamp();

        return (int)round($runtimeElapsedSeconds / $this->runtimeSeconds() * 100);
    }

    public function episodeInfo(): string
    {
        $string = '';
        if ($this->season() !== 0) {
            $string .= "S{$this->season()}";
        }
        if ($this->episode() !== 0) {
            $string .= "E{$this->episode}";
        }

        return $string;
    }
}