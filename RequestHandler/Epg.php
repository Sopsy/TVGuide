<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler;

use DateTimeImmutable;
use IntlDateFormatter;
use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use Library\ExceptionHandler\Exception\PageNotFoundException;
use Library\Template\View\DefaultTemplate;
use TVGuide\Channel\Exception\ChannelNotFound;
use TVGuide\Channel\Repository\Channel;
use TVGuide\ChannelGroup\Exception\ChannelGroupNotFound;
use TVGuide\ChannelGroup\Repository\ChannelGroup;
use TVGuide\Program\Repository\Program;
use TVGuide\View\Epg as EpgView;

use function _;
use function array_key_exists;
use function mb_strtolower;
use function sprintf;

final readonly class Epg implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        $isFrontPage =
            $request->attribute('date') === '' &&
            $request->attribute('group') === '' &&
            $request->attribute('channel') === '';
        $isUpcomingView = $request->attribute('date') === '';
        $channelGroupSlug = $request->attribute('group');
        $isDefaultChannelGroup = $request->attribute('group') === '' && $request->attribute('channel') === '';
        $channelSlug = $request->attribute('channel');
        $isSingleChannel = $request->attribute('channel') !== '';
        $date = new DateTimeImmutable($request->attribute('date'), $request->user()->timezone());

        $channelGroupRepo = new ChannelGroup($this->db);
        $channelGroups = $channelGroupRepo->byUser($request->user(), true);

        if ($channelGroupSlug === '') {
            $channelGroupSlug = $channelGroupRepo->defaultSlug();
        }

        if (!array_key_exists($channelGroupSlug, $channelGroups)) {
            throw new PageNotFoundException(_('Channel group does not exist'));
        }

        try {
            $channelGroup = $channelGroupRepo->byId($channelGroups[$channelGroupSlug]->id(), $request->user()->isAdmin());
        } catch (ChannelGroupNotFound) {
            throw new PageNotFoundException(_('Channel group does not exist'));
        }

        $channelRepo = new Channel($this->db);
        if ($isSingleChannel) {
            try {
                $channel = $channelRepo->bySlug($channelSlug);
            } catch (ChannelNotFound) {
                throw new PageNotFoundException(_('Channel does not exist'));
            }

            if (!$channel->isVisible() && !$request->user()->isAdmin()) {
                throw new PageNotFoundException(_('Channel does not exist'));
            }

            $pageTitle = $channel->name();
            $epgData = $channelRepo->byChannelsWithPrograms($isUpcomingView, $date, $channel);
        } else {
            $pageTitle = $channelGroup->name();
            $epgData = $channelRepo->byChannelsWithPrograms($isUpcomingView, $date, ...$channelGroup->channels());
        }

        $formatter = (new IntlDateFormatter(
            $request->user()->locale(),
            IntlDateFormatter::RELATIVE_SHORT,
            IntlDateFormatter::NONE,
            $request->user()->timezone()
        ));

        if ($isFrontPage) {
            $pageTitle = sprintf(_('TV programs %s'), $formatter->format($date));
        } elseif ($isUpcomingView) {
            $pageTitle .= ' ' . $formatter->format($date);
        } else {
            $pageTitle .= ' ' . (new IntlDateFormatter(
                    $request->user()->locale(),
                    IntlDateFormatter::SHORT,
                    IntlDateFormatter::NONE,
                    $request->user()->timezone()
                ))->format($date);
        }

        [$first, $last] = (new Program($this->db))->firstAndLastDateTime();

        $userHasCustomChannelGroup = false;
        foreach ($channelGroups as $group) {
            if ($group->userId() === $request->user()->id()) {
                $userHasCustomChannelGroup = true;
                break;
            }
        }

        $metaDesc = _(
            'What\'s on TV today? Here you\'ll find whole day\'s TV line-up. ' .
            'Open up our TV-guide and see this week\'s upcoming and past television programs from all channels.'
        );

        $isToday = $date->format('Y-m-d') === (
                new DateTimeImmutable('', $request->user()->timezone())
            )->format('Y-m-d');

        $view = new EpgView(
            $request,
            $pageTitle,
            $epgData,
            $channelGroups,
            $userHasCustomChannelGroup,
            $isFrontPage,
            $isDefaultChannelGroup,
            $isSingleChannel,
            $isUpcomingView,
            $isToday,
            $date,
            $first,
            $last,
            $channelGroup
        );

        if ($isFrontPage) {
            $pageTitle .= ' - ' . _('See TV guide') . ' (' . mb_strtolower(_('All channels')) . ')';
        }

        return (new Response(
            (new DefaultTemplate(
                $request,
                $this->db,
                $view,
                $pageTitle,
                $metaDesc,
            ))->render()
        ));
    }
}