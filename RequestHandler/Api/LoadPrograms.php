<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Api;

use DateTimeImmutable;
use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use TVGuide\Program\Repository\Program as ProgramRepo;
use TVGuide\View\Snippet\EpgProgramList;

final readonly class LoadPrograms implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        $channelId = (int)$request->bodyParam('channel');
        $lastStartTime = (new DateTimeImmutable())->setTimestamp((int)$request->bodyParam('start'));
        $lastEndTime = (new DateTimeImmutable())->setTimestamp((int)$request->bodyParam('end'));

        $programs = (new ProgramRepo($this->db))->programSetByChannelId($channelId, $lastEndTime, 100);

        return new Response((new EpgProgramList($request, $lastStartTime, false, $programs))->render());
    }
}
