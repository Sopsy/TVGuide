<?php
declare(strict_types=1);

namespace TVGuide\View;

use DateInterval;
use DateTimeImmutable;
use IntlDateFormatter;
use Library\HttpMessage\Contract\Request;
use Library\Template\Contract\View;
use TVGuide\Channel\Contract\Channel;
use TVGuide\ChannelGroup\Contract\ChannelGroup;
use TVGuide\ChannelGroup\Contract\ChannelGroupWithChannels;
use TVGuide\View\Snippet\EpgChannel as EpgChannelSnippet;
use TVGuide\View\Snippet\SeoText;

use function _;
use function array_values;
use function e;
use function ob_get_clean;
use function ob_start;

final readonly class Epg implements View
{
    private string $baseUrl;
    private IntlDateFormatter $dayFormatter;
    private bool $isAdmin;
    private DateTimeImmutable $nextDate;
    private DateTimeImmutable $prevDate;
    private IntlDateFormatter $shortWeekdayFormatter;

    /**
     * @param Request $request
     * @param string $pageTitle
     * @param Channel[] $epgData
     * @param ChannelGroup[] $availableChannelGroups
     * @param bool $userHasCustomChannelGroup
     * @param bool $isFrontPage
     * @param bool $isDefaultChannelGroup
     * @param bool $isSingleChannel
     * @param bool $isUpcomingView
     * @param bool $isToday
     * @param DateTimeImmutable $date
     * @param DateTimeImmutable $firstProgramDateTime
     * @param DateTimeImmutable $lastProgramDateTime
     * @param ChannelGroupWithChannels $channelGroup
     */
    public function __construct(
        private Request $request,
        private string $pageTitle,
        private array $epgData,
        private array $availableChannelGroups,
        private bool $userHasCustomChannelGroup,
        private bool $isFrontPage,
        private bool $isDefaultChannelGroup,
        private bool $isSingleChannel,
        private bool $isUpcomingView,
        private bool $isToday,
        private DateTimeImmutable $date,
        private DateTimeImmutable $firstProgramDateTime,
        private DateTimeImmutable $lastProgramDateTime,
        private ChannelGroupWithChannels $channelGroup,
    ) {
        $this->baseUrl = '/tv-ohjelmat';

        $this->dayFormatter = (new IntlDateFormatter(
            $request->user()->locale(),
            IntlDateFormatter::RELATIVE_SHORT,
            IntlDateFormatter::NONE,
            $request->user()->timezone(),
        ));

        $this->isAdmin = $this->request->user()->isAdmin();
        $this->nextDate = $this->date->add(new DateInterval('P1D'));
        $this->prevDate = $this->date->sub(new DateInterval('P1D'));

        $this->shortWeekdayFormatter = (new IntlDateFormatter(
            $request->user()->locale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $request->user()->timezone(),
            IntlDateFormatter::GREGORIAN,
            'EEE'
        ));
    }

    public function render(): string
    {
        $upcomingLink = $this->baseUrl;
        if ($this->isDefaultChannelGroup) {
            $upcomingLink .= '/';
        } elseif ($this->isSingleChannel) {
            $upcomingLink .= array_values($this->epgData)[0]->url();
        } else {
            $upcomingLink .= $this->channelGroup->url();
        }

        $wholeDayLink = $this->baseUrl;
        if ($this->isDefaultChannelGroup) {
            $wholeDayLink .= '/' . $this->date->format('Y-m-d');
        } elseif ($this->isSingleChannel) {
            $wholeDayLink .= array_values($this->epgData)[0]->url($this->date);
        } else {
            $wholeDayLink .= $this->channelGroup->url($this->date);
        }

        $prevDateLink = $this->baseUrl;
        if ($this->isDefaultChannelGroup) {
            $prevDateLink .= '/' . $this->prevDate->format('Y-m-d');
        } elseif ($this->isSingleChannel) {
            $prevDateLink .= array_values($this->epgData)[0]->url($this->prevDate);
        } else {
            $prevDateLink .= $this->channelGroup->url($this->prevDate);
        }

        $nextDateLink = $this->baseUrl;
        if ($this->isDefaultChannelGroup) {
            $nextDateLink .= '/' . $this->nextDate->format('Y-m-d');
        } elseif ($this->isSingleChannel) {
            $nextDateLink .= array_values($this->epgData)[0]->url($this->nextDate);
        } else {
            $nextDateLink .= $this->channelGroup->url($this->nextDate);
        }

        ob_start();
        ?>
        <section id="tv-guide">
        <template id="channel-group-dropdown-content">
            <h4><?= _('Channel groups') ?></h4>
            <nav>
                <?php if ($this->isAdmin || !$this->userHasCustomChannelGroup): ?>
                    <button class="text-button" data-action="TVGuide.createChannelGroup">
                        <span class="icon-plus"></span> <?= _('Create your own') ?></button>
                <?php endif ?>

                <?php foreach ($this->availableChannelGroups as $channelGroup): ?>
                    <div data-group-id="<?= $channelGroup->id() ?>"<?=
                    $this->channelGroup->id() === $channelGroup->id() ? ' class="cur"': '' ?>>
                        <a class="grow" href="<?= $this->baseUrl ?>/group/<?= e($channelGroup->slug()) ?>/<?=
                        $this->isUpcomingView ? '' : $this->date->format('Y-m-d')
                        ?>"><?= e($channelGroup->name()) ?></a>
                        <?php if ($this->isAdmin || $this->request->user()->id() === $channelGroup->userId()): ?>
                            <button class="icon-pencil-line text-button" data-action="TVGuide.editChannelGroup"
                                    data-group-id="<?= $channelGroup->id() ?>"></button>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </nav>
        </template>

        <header id="header">
            <div>
                <h1>
                    <a href="<?= $this->baseUrl ?>/"><?= e($this->pageTitle) ?></a>
                    (<?= $this->shortWeekdayFormatter->format($this->date) ?>)
                </h1>
            </div>
            <label class="right" id="epg-search">
                <span class="icon-magnifier"></span>
                <input type="search"
                    data-base-url="<?= $this->baseUrl ?>"
                    data-date="<?= $this->date->format('Y-m-d') ?>" />
            </label>
        </header>
        <nav class="header-nav epg-nav">
            <?php if ($this->isSingleChannel): ?>
                <a href="<?= $this->baseUrl ?>/<?= $this->date->format('Y-m-d') ?>" class="button return-to">
                    <span class="icon-arrow-left"></span>
                    <span><?= _('Return') ?></span>
                </a>
            <?php endif ?>
            <div class="button-group">
                <a class="button icon-chevron-left" href="<?= $prevDateLink ?>"></a>
                <label class="date-input text">
                    <span class="icon-calendar-full"></span>
                    <input id="date-change-input" type="date" value="<?= $this->date->format('Y-m-d') ?>"
                           min="<?= $this->firstProgramDateTime->format('Y-m-d') ?>"
                           max="<?= $this->lastProgramDateTime->format('Y-m-d') ?>"
                    >
                    <?= $this->dayFormatter->format($this->date) ?>
                    (<?= $this->shortWeekdayFormatter->format($this->date) ?>)
                </label>
                <a class="button icon-chevron-right" href="<?= $nextDateLink ?>"></a>
            </div>
            <?php if (!$this->isSingleChannel): ?>
                <button id="channel-group-dropdown" class="button-group" data-action="TVGuide.channelGroupDropdown">
                    <span class="text"><?= e($this->channelGroup->name()) ?></span>
                    <span class="icon-chevron-down button"></span>
                </button>
            <?php endif ?>
            <?php if ($this->isToday): ?>
                <nav>
                    <a class="toggle-button<?= $this->isUpcomingView ? ' active' : '' ?>" href="<?= $upcomingLink ?>"><?= _('Upcoming') ?></a>
                    <a class="toggle-button<?= !$this->isUpcomingView ? ' active' : '' ?>" href="<?= $wholeDayLink ?>"><?= _('Whole day') ?></a>
                </nav>
            <?php endif ?>
        </nav>

        <div class="epg-channel-list">
            <?php
            $i = 0;
            foreach ($this->epgData as $channel) {
                ++$i;

                echo (new EpgChannelSnippet($this->request, $channel, $this->date, $this->isSingleChannel))->render();
            }
            ?>
        </div>

        <?php if ($this->isFrontPage): ?>
            <div class="footer-tv-guide">
                <div class="footer-heading">
                    <img id="logo" src="/img/logo-icon.svg" alt="<?= _("tv guide and television programs") ?>">
                    <h2><?= _('TV-guide – program information & television programs')?></h2>
                </div>
                <p><?= _('What\'s on the TV today? From this TV-guide you will find all channels and every date.')?></p>
                <p><?= _('The program listing offers upcoming and past broadcast information. You can change the date or channel easily from the menu, so you can quickly find the programs you are looking for.')?></p>
                <p><?= _('You can also browse the calendar backwards, so you can see past tv broadcasts from previous days. Nowadays, it is really easy to watch replays of past broadcasts online.')?></p>
            </div>
        <?php endif ?>

        </section>
        <?php

        return ob_get_clean();
    }
}