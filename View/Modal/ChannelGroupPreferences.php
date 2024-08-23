<?php
declare(strict_types=1);

namespace TVGuide\View\Modal;

use Library\HttpMessage\Contract\Request;
use Library\Template\Contract\View;
use TVGuide\Channel\Contract\Channel as ChannelInterface;
use TVGuide\ChannelGroup\Contract\ChannelGroupWithChannels;

use function _;
use function e;
use function in_array;
use function ob_get_clean;
use function ob_start;

final readonly class ChannelGroupPreferences implements View
{
    /** @var int[] */
    private array $selectedChannels;

    /**
     * @param Request $request
     * @param ChannelInterface[] $channelList
     * @param ChannelGroupWithChannels|null $channelGroup
     */
    public function __construct(
        private Request $request,
        private array $channelList,
        private ?ChannelGroupWithChannels $channelGroup,
    ) {
        $selectedChannels = [];
        if ($this->channelGroup !== null) {
            foreach ($this->channelGroup->channels() as $channel) {
                $selectedChannels[] = $channel->id();
            }
        }

        $this->selectedChannels = $selectedChannels;
    }

    public function render(): string
    {
        ob_start();
        ?>
        <form action="/api/tv-guide/channel-group/<?= $this->channelGroup === null ? 'create' : 'edit' ?>" method="post" data-reload>
            <?php if ($this->channelGroup !== null): ?>
                <input type="hidden" name="id" value="<?= $this->channelGroup->id() ?>" />
            <?php endif ?>
            <input type="text" name="name" placeholder="<?= _('Channel group name')  ?>"
                   value="<?= $this->channelGroup ? e($this->channelGroup->name()) : '' ?>" required>
            <?php if ($this->request->user()->isAdmin()): ?>
                <label class="toggle-checkbox">
                    <?= _('Available to all users') ?>
                    <input type="checkbox" name="public"<?=
                        $this->channelGroup && $this->channelGroup->isPublic() ? ' checked' : '' ?> />
                    <div></div>
                </label>
                <label class="toggle-checkbox">
                    <?= _('Set as default') ?>
                    <input type="checkbox" name="default"<?=
                        $this->channelGroup && $this->channelGroup->isDefault() ? ' checked' : '' ?> />
                    <div></div>
                </label>
            <?php endif ?>
            <h4><?= _('Choose channels') ?></h4>
            <?php foreach ($this->channelList as $channel): ?>
                <label class="toggle-checkbox">
                    <?= e($channel->name()) ?>
                    <input type="checkbox" name="channels[]" value="<?= $channel->id() ?>"<?=
                        in_array($channel->id(), $this->selectedChannels, true) ? ' checked' : '' ?> />
                    <div></div>
                </label>
            <?php endforeach ?>
            <footer>
                <?php if ($this->channelGroup === null): ?>
                    <button type="submit">
                        <?= _('Create a channel group') ?>
                    </button>
                <?php else: ?>
                    <button type="submit"><?= _('Save changes') ?></button>
                    <button type="button" data-action="TVGuide.deleteChannelGroup" data-group-id="<?= $this->channelGroup->id() ?>">
                        <span class="icon-trash2"></span>
                    </button>
                <?php endif ?>
            </footer>
        </form>
        <?php

        return ob_get_clean();
    }
}