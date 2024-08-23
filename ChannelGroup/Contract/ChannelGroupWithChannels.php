<?php
declare(strict_types=1);

namespace TVGuide\ChannelGroup\Contract;

use TVGuide\Channel\Contract\Channel;

interface ChannelGroupWithChannels extends ChannelGroup
{
    /**
     * @return Channel[]
     */
    public function channels(): array;
}