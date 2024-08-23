<?php
declare(strict_types=1);

namespace TVGuide\Program\Contract;

use DateTimeImmutable;

interface Program
{
    public function id(): int;

    public function title(): string;

    public function channelId(): int;

    public function description(): string;

    public function startTime(): DateTimeImmutable;

    public function endTime(): DateTimeImmutable;

    public function season(): int;

    public function episode(): int;

    public function runtimeSeconds(): int;

    public function episodeInfo(): string;

    public function runtimeElapsedPercentage(): int;

    public function hasEnded(): bool;

    public function isRunning(): bool;
}