<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Modal;

use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use TVGuide\Channel\Repository\Channel as ChannelRepo;
use TVGuide\View\Modal\ChannelGroupPreferences;

final readonly class ChannelGroupCreate implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        $channelList = (new ChannelRepo($this->db))->all();

        return new Response(
            (new ChannelGroupPreferences(
                $request,
                $channelList,
                null,
            ))->render()
        );
    }
}