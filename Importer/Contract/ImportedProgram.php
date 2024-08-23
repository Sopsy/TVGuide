<?php
declare(strict_types=1);

namespace TVGuide\Importer\Contract;

use DateTimeImmutable;

interface ImportedProgram
{
    public function title(): string;

    public function description(): string;

    public function startTime(): DateTimeImmutable;

    public function endTime(): DateTimeImmutable;

    public function season(): int;

    public function episode(): int;

    public function episodeCount(): int;
}