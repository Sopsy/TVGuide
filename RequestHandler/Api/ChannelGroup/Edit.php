<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Api\ChannelGroup;

use Throwable;
use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use Library\ExceptionHandler\Exception\PublicErrorException;
use TVGuide\ChannelGroup\Exception\ChannelGroupNotFound;
use TVGuide\ChannelGroup\Helper\Validator;
use TVGuide\ChannelGroup\Repository\ChannelGroup as ChannelGroupRepo;

use function _;
use function array_map;

final readonly class Edit implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        $id = (int)$request->bodyParam('id', '0');
        $name = $request->bodyParam('name');
        $isPublic = $request->bodyParam('public', 'off') === 'on' && $request->user()->isAdmin();
        $setDefault = $request->bodyParam('default', 'off') === 'on' && $request->user()->isAdmin();
        $channels = array_map('\intval', $request->bodyParamArray('channels'));

        $channelGroupRepo = new ChannelGroupRepo($this->db);

        try {
            $channelGroup = $channelGroupRepo->byId($id, $request->user()->isAdmin());
        } catch (ChannelGroupNotFound $e) {
            throw new PublicErrorException(_('Channel group does not exist'), 404, $e);
        }

        (new Validator($name, $channels))->validate();

        if ($channelGroupRepo->nameExists($name, $id)) {
            throw new PublicErrorException(_('This name is already in use'), 400);
        }

        if (!$request->user()->isAdmin() && ($request->user()->id() !== $channelGroup->userId() || $channelGroup->isPublic())) {
            throw new PublicErrorException(_('You don\'t have a permission to edit this channel group'), 403);
        }

        if ($channelGroup->name() !== $name) {
            $channelGroupRepo->setName($channelGroup, $name);
        }

        if ($channelGroup->isPublic() !== $isPublic) {
            if ($channelGroup->isDefault()) {
                throw new PublicErrorException(_('Default channel group must be available to all users'), 400);
            }
            $channelGroupRepo->setIsPublic($channelGroup, $isPublic);
        }

        if (!$channelGroup->isDefault() && $setDefault) {
            if (!$channelGroup->isPublic()) {
                throw new PublicErrorException(_('Default channel group must be available to all users'), 400);
            }
            $channelGroupRepo->setDefault($channelGroup);
        }

        try {
            $channelGroupRepo->setChannels($channelGroup, ...$channels);
        } catch (Throwable $e) {
            throw new PublicErrorException(_('Some of the channels you have selected do not exist. Please refresh this page and try again.'), 400, $e);
        }

        return new Response(_('Changes saved'));
    }
}