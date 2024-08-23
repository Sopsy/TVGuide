<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Modal;

use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use Library\ExceptionHandler\Exception\PublicErrorException;
use TVGuide\Channel\Repository\Channel as ChannelRepo;
use TVGuide\ChannelGroup\Exception\ChannelGroupNotFound;
use TVGuide\ChannelGroup\Repository\ChannelGroup as ChannelGroupRepo;
use TVGuide\View\Modal\ChannelGroupPreferences;

use function _;

final readonly class ChannelGroupEdit implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        $id = (int)$request->bodyParam('id', '0');

        try {
            $channelGroup = (new ChannelGroupRepo($this->db))->byId($id, $request->user()->isAdmin());
        } catch (ChannelGroupNotFound $e) {
            throw new PublicErrorException(_('Channel group does not exist'), 404, $e);
        }

        $channelList = (new ChannelRepo($this->db))->all();

        if (!$request->user()->isAdmin() && ($request->user()->id() !== $channelGroup->userId() || $channelGroup->isPublic())) {
            throw new PublicErrorException(_('You don\'t have a permission to edit this channel group'), 403);
        }

        return new Response(
            (new ChannelGroupPreferences(
                $request,
                $channelList,
                $channelGroup,
            ))->render()
        );
    }
}