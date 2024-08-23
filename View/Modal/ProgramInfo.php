<?php
declare(strict_types=1);

namespace TVGuide\View\Modal;

use IntlDateFormatter;
use Library\HttpMessage\Contract\Request;
use Library\Template\Contract\View;
use TVGuide\Channel\Contract\Channel;
use TVGuide\Program\Contract\Program;

use function _;
use function count;
use function e;
use function ob_get_clean;
use function ob_start;

final readonly class ProgramInfo implements View
{
    /** @var Program[] */
    private array $broadcasts;
    private IntlDateFormatter $dateFormatter;
    private IntlDateFormatter $timeFormatter;

    /**
     * @param Request $request
     * @param array<int, Channel> $channels
     * @param Program $program
     * @param Program ...$broadcasts
     */
    public function __construct(
        Request $request,
        private array $channels,
        private Program $program,
        Program ...$broadcasts
    ) {
        $this->broadcasts = $broadcasts;

        $this->dateFormatter = (new IntlDateFormatter(
            $request->user()->locale(),
            IntlDateFormatter::RELATIVE_MEDIUM,
            IntlDateFormatter::SHORT,
            $request->user()->timezone()
        ));

        $this->timeFormatter = (new IntlDateFormatter(
            $request->user()->locale(), IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $request->user()->timezone()
        ));
    }

    public function render(): string
    {
        ob_start();
        ?>

        <div
            id="<?= $this->program->id() ?>"
            class="epg-program<?= ($this->program->isRunning() ? ' running' : '') .
            ($this->program->hasEnded() ? ' ended' : '') ?>"
            data-start-time="<?= $this->program->startTime()->getTimestamp() ?>"
            data-end-time="<?= $this->program->endTime()->getTimestamp() ?>"
        >
            <div>
                <p class="channel-name"><?= e($this->channels[$this->program->channelId()]->name()) ?></p>
            </div>
            <?php if ($this->program->isRunning()): ?>
                <div class="progress">
                    <progress value="<?= $this->program->runtimeElapsedPercentage() ?>" max="100"></progress>
                    <span class="start"><?= $this->timeFormatter->format($this->program->startTime()) ?></span>
                    <span class="end"><?= $this->timeFormatter->format($this->program->endTime()) ?></span>
                </div>
            <?php else: ?>
                <p class="start-time">
                    <?= $this->dateFormatter->format($this->program->startTime()) ?>
                </p>
            <?php endif ?>
            <p class="description"><?= e($this->program->description()) ?></p>
        </div>

        <?php if (count($this->broadcasts) !== 0): ?>
            <p class="upcoming-toggle" data-action="TVGuide.toggleUpcomingListVisibility"><?= _('Show upcoming broadcasts') ?> (<?= count($this->broadcasts) ?>)</p>
            <div class="broadcast-list hidden">
                <?php foreach ($this->broadcasts as $broadcast): ?>
                    <div>
                        <div>
                            <p class="start-time"><?= $this->dateFormatter->format(
                                    $broadcast->startTime()
                                ) ?></p>
                            <img
                                src="<?= $this->channels[$broadcast->channelId()]->logoUrl() ?>"
                                alt="<?= e($this->channels[$broadcast->channelId()]->name()) ?>"
                            />
                        </div>
                        <div>
                            <p class="description">
                                <?= e($broadcast->description()) ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <?php

        return ob_get_clean();
    }
}