<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Api\ChannelGroup;

use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use Library\ExceptionHandler\Exception\PublicErrorException;
use TVGuide\ChannelGroup\Exception\ChannelGroupNotFound;
use TVGuide\ChannelGroup\Repository\ChannelGroup as ChannelGroupRepo;

use function _;

final readonly class Delete implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        $id = (int)$request->bodyParam('id', '0');
        $channelGroupRepo = new ChannelGroupRepo($this->db);

        try {
            $channelGroup = $channelGroupRepo->byId($id, $request->user()->isAdmin());
        } catch (ChannelGroupNotFound $e) {
            throw new PublicErrorException(_('Channel group does not exist'), 404, $e);
        }

        if (!$request->user()->isAdmin() && ($request->user()->id() !== $channelGroup->userId() || $channelGroup->isPublic())) {
            throw new PublicErrorException(_('You don\'t have a permission to delete this channel group'), 403);
        }

        if ($channelGroup->isDefault()) {
            throw new PublicErrorException(_('The default channel group can\'t be deleted'), 403);
        }

        $channelGroupRepo->delete($channelGroup);

        return new Response(_('Channel group deleted'));
    }
}