<?php
declare(strict_types=1);

namespace TVGuide\ChannelGroup\Model;

use DateTimeInterface;
use TVGuide\ChannelGroup\Contract\ChannelGroup as ChannelGroupInterface;

final readonly class ChannelGroup implements ChannelGroupInterface
{
    public function __construct(
        private int $id,
        private string $name,
        private string $slug,
        private int $userId,
        private bool $isPublic,
        private bool $isDefault,
        private int $position,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function url(DateTimeInterface $date = null): string
    {
        $return = "/group/{$this->slug}/";

        if ($date !== null) {
            $return .= $date->format('Y-m-d');
        }

        return $return;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function position(): int
    {
        return $this->position;
    }
}