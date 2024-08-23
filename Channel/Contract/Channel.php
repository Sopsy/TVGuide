<?php
declare(strict_types=1);

namespace TVGuide\Channel\Contract;

use DateTimeInterface;
use TVGuide\Program\Contract\Program;

interface Channel
{
    public function id(): int;

    public function originId(): string;

    public function name(): string;

    public function slug(): string;

    public function url(DateTimeInterface $date = null): string;

    public function position(): int;

    public function isVisible(): bool;

    /**
     * @return Program[]
     */
    public function programs(): array;

    public function hasLogo(): bool;

    public function logoUrl(): string;
}