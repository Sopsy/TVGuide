<?php
declare(strict_types=1);

namespace TVGuide\Importer\Model;

use DateTimeImmutable;
use TVGuide\Importer\Contract\ImportedProgram as ImportedProgramInterface;

final readonly class ImportedProgram implements ImportedProgramInterface
{
    public function __construct(
        private string $title,
        private string $description,
        private DateTimeImmutable $startTime,
        private DateTimeImmutable $endTime,
        private int $season = 0,
        private int $episode = 0,
        private int $episodeCount = 0,
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
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

    public function episodeCount(): int
    {
        return $this->episodeCount;
    }
}