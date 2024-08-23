<?php
declare(strict_types=1);

namespace TVGuide\ChannelGroup\Model;

use DateTimeInterface;
use TVGuide\Channel\Contract\Channel;
use TVGuide\ChannelGroup\Contract\ChannelGroup;
use TVGuide\ChannelGroup\Contract\ChannelGroupWithChannels as ChannelGroupWithChannelsInterface;

final readonly class ChannelGroupWithChannels implements ChannelGroupWithChannelsInterface
{
    /** @var Channel[] */
    private array $channels;

    public function __construct(
        private ChannelGroup $channelGroup,
        Channel ...$channels
    ) {
        $this->channels = $channels;
    }

    public function id(): int
    {
        return $this->channelGroup->id();
    }

    public function name(): string
    {
        return $this->channelGroup->name();
    }

    public function slug(): string
    {
        return $this->channelGroup->slug();
    }

    public function url(DateTimeInterface $date = null): string
    {
        return $this->channelGroup->url($date);
    }

    public function isPublic(): bool
    {
        return $this->channelGroup->isPublic();
    }

    public function isDefault(): bool
    {
        return $this->channelGroup->isDefault();
    }

    public function userId(): int
    {
        return $this->channelGroup->userId();
    }

    public function position(): int
    {
        return $this->channelGroup->position();
    }

    public function channels(): array
    {
        return $this->channels;
    }
}