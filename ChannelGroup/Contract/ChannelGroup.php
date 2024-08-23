<?php
declare(strict_types=1);

namespace TVGuide\ChannelGroup\Contract;

use DateTimeInterface;

interface ChannelGroup
{
    public function id(): int;

    public function name(): string;

    public function slug(): string;

    public function url(DateTimeInterface $date = null): string;

    public function isPublic(): bool;

    public function isDefault(): bool;

    public function userId(): int;

    public function position(): int;
}