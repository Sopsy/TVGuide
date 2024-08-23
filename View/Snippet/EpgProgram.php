<?php
declare(strict_types=1);

namespace TVGuide\View\Snippet;

use IntlDateFormatter;
use Library\Template\Contract\View;
use TVGuide\Program\Contract\Program;

use function ob_get_clean;
use function ob_start;

final readonly class EpgProgram implements View
{
    public function __construct(
        private Program $program,
        private IntlDateFormatter $formatter
    ) {
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
            data-progress="<?= $this->program->runtimeElapsedPercentage() ?>"
        >
            <div>
                <span class="time"><?= $this->formatter->format($this->program->startTime()) ?></span>
            </div>
            <div>
                <button
                    class="program-title text-button" data-action="TVGuide.programInfoModal"
                    data-program-id="<?= $this->program->id() ?>"
                    data-running="<?= $this->program->isRunning() ?>"
                >
                    <?= $this->program->title() ?>
                    <?php if ($this->program->episodeInfo() !== ''): ?>
                        <span class="episode"><?= $this->program->episodeInfo() ?></span>
                    <?php endif ?>
                </button>
                <div class="description"><?= $this->program->description() ?></div>

                <div class="progress">
                    <progress value="<?= $this->program->runtimeElapsedPercentage() ?>" max="100"></progress>
                    <span class="start"><?= $this->formatter->format($this->program->startTime()) ?></span>
                    <span class="end"><?= $this->formatter->format($this->program->endTime()) ?></span>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}