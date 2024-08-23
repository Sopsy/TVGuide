<?php
declare(strict_types=1);

namespace TVGuide\View\Snippet;

use DateInterval;
use DateTimeImmutable;
use IntlDateFormatter;
use Library\HttpMessage\Contract\Request;
use Library\Template\Contract\View;
use TVGuide\Program\Contract\Program;

use function ob_get_clean;
use function ob_start;

final readonly class EpgProgramList implements View
{
    private IntlDateFormatter $shortDateFormatter;
    private IntlDateFormatter $shortTimeFormatter;
    private IntlDateFormatter $weekdayFormatter;

    /**
     * @param Request $request
     * @param DateTimeImmutable $date
     * @param bool $singleChannel
     * @param Program[] $programs
     */
    public function __construct(
        private Request $request,
        private DateTimeImmutable $date,
        private bool $singleChannel,
        private array $programs,
    ) {
        $this->shortDateFormatter = (new IntlDateFormatter(
            $this->request->user()->locale(),
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            $this->request->user()->timezone(),
            IntlDateFormatter::GREGORIAN,
            'd.M.'
        ));

        $this->shortTimeFormatter = (new IntlDateFormatter(
            $request->user()->locale(), IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $request->user()->timezone()
        ));

        $this->weekdayFormatter = (new IntlDateFormatter(
            $request->user()->locale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $request->user()->timezone(),
            IntlDateFormatter::GREGORIAN,
            'cccc'
        ));
    }

    public function render(): string
    {
        ob_start();
        ?>

        <?php $listDate = $this->date ?>
        <?php foreach ($this->programs as $program): ?>
            <?php if ($program->startTime()->format('Y-m-d') > $listDate->format('Y-m-d')): ?>
                <div class="daybreak">
                    <?= $this->weekdayFormatter->format($listDate->add(new DateInterval('P1D'))) ?>
                    <?= $this->shortDateFormatter->format($listDate->add(new DateInterval('P1D'))) ?>
                </div>
                <?php $listDate = $listDate->add(new DateInterval('P1D')) ?>
            <?php endif ?>
            <?= (new EpgProgram($program, $this->shortTimeFormatter))->render() ?>
            <?php if (
                $program->endTime()->format('Y-m-d') === $this->date->format('Y-m-d') &&
                $program->startTime()->format('Y-m-d') < $listDate->format('Y-m-d')
            ): ?>
                <div class="daybreak">
                    <?= $this->weekdayFormatter->format($this->date) ?>
                    <?= $this->shortDateFormatter->format($this->date) ?>
                </div>
            <?php endif ?>
        <?php endforeach ?>

        <?php

        return ob_get_clean();
    }
}