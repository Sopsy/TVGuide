<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Modal;

use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use Library\ExceptionHandler\Exception\PageNotFoundException;
use TVGuide\Channel\Repository\Channel;
use TVGuide\Program\Exception\ProgramNotFound;
use TVGuide\Program\Repository\Program;
use TVGuide\View\Modal\ProgramInfo as ProgramInfoView;

use function _;

final readonly class ProgramInfo implements RequestHandler
{
    public function __construct(private DbLoader $db)
    {
    }

    public function handle(Request $request): Response
    {
        $programId = (int)$request->bodyParam('program_id', '0');

        $channels = (new Channel($this->db))->all();
        $programRepository = new Program($this->db);

        try {
            $program = $programRepository->byId($programId);
        } catch (ProgramNotFound) {
            throw new PageNotFoundException(_('Program does not exist. Refresh this page and try again.'));
        }

        $broadcasts = $programRepository->getUpcomingBroadcasts($program, 100);

        return new Response(
            (new ProgramInfoView(
                $request, $channels, $program, ...$broadcasts
            ))->render()
        );
    }
}