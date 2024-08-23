<?php
declare(strict_types=1);

namespace TVGuide\View\Snippet;

use Library\Template\Contract\View;
use TVGuide\Channel\Contract\Channel;

use function e;
use function ob_get_clean;
use function ob_start;

final readonly class ChannelLogo implements View
{
    public function __construct(
        private Channel $channel
    ) {
    }

    public function render(): string
    {
        ob_start();
        ?>
            <?php if ($this->channel->hasLogo()): ?>
                <img
                    class="logo"
                    src="<?= $this->channel->logoUrl() ?>"
                    alt="<?= e($this->channel->name()) ?>"
                >
            <?php else: ?>
                <span class="logo"><?= e($this->channel->name()) ?></span>
            <?php endif ?>
        <?php

        return ob_get_clean();
    }
}