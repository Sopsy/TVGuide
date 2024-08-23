<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Api\ChannelGroup;

use Throwable;
use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Contract\Response;
use Library\HttpMessage\Message\EmptyResponse;
use Library\ExceptionHandler\Exception\PublicErrorException;
use TVGuide\ChannelGroup\Helper\Validator;
use TVGuide\ChannelGroup\Repository\ChannelGroup as ChannelGroupRepo;

use function _;
use function array_map;
use function count;

final readonly class Create implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        $name = $request->bodyParam('name', '');
        $channels = $request->bodyParamArray('channels');
        $isPublic = $request->bodyParam('public', 'off') === 'on' && $request->user()->isAdmin();
        $setDefault = $request->bodyParam('default', 'off') === 'on' && $request->user()->isAdmin();

        $channelGroupRepo = new ChannelGroupRepo($this->db);
        $channels = array_map('\intval', $channels);

        (new Validator($name, $channels))->validate();

        if ($channelGroupRepo->nameExists($name)) {
            throw new PublicErrorException(_('This name is already in use'), 400);
        }

        if (!$request->user()->isAdmin() && count($channelGroupRepo->byUser($request->user(), false)) !== 0) {
            throw new PublicErrorException(_('You may only have one custom channel group'), 400);
        }

        if ($setDefault && !$isPublic) {
            throw new PublicErrorException(_('Default channel group must be available to all users'), 400);
        }

        $channelGroup = $channelGroupRepo->add($name, $request->user()->id(), $isPublic);

        if ($setDefault) {
            $channelGroupRepo->setDefault($channelGroup);
        }

        try {
            $channelGroupRepo->setChannels($channelGroup, ...$channels);
        } catch (Throwable $e) {
            throw new PublicErrorException(_('Some of the channels you have selected do not exist. Please refresh this page and try again.'), 400, $e);
        }

        return new EmptyResponse();
    }
}