<?php
declare(strict_types=1);

namespace TVGuide\View\Snippet;

use DateTimeImmutable;
use Library\HttpMessage\Contract\Request;
use Library\Template\Contract\View;
use TVGuide\Channel\Contract\Channel;

use function _;
use function count;
use function ob_get_clean;
use function ob_start;

final readonly class EpgChannel implements View
{
    public function __construct(
        private Request $request,
        private Channel $channel,
        private DateTimeImmutable $date,
        private bool $singleChannel,
    ) {
    }

    public function render(): string
    {
        ob_start();
        ?>
        <div id="<?= e($this->channel->slug()) ?>" data-channel-id="<?= $this->channel->id() ?>"
            class="card with-title epg-channel <?= $this->singleChannel ? 'single' : '' ?>">
            <a class="title" href="/tv-ohjelmat<?= $this->channel->url($this->date) ?>">
                <?= (new ChannelLogo($this->channel))->render() ?>
            </a>
            <?php if (count($this->channel->programs()) === 0): ?>
                <p class="no-epg-data"><?= _('No EPG data for the selected date.') ?></p>
            <?php else: ?>
                <?= (new EpgProgramList(
                    $this->request, $this->date, $this->singleChannel, $this->channel->programs()
                     ))->render() ?>
            <?php endif ?>
        </div>
        <?php

        return ob_get_clean();
    }
}