<?php
declare(strict_types=1);

namespace TVGuide;

use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Contract\Response;
use Library\Router\RegexMatch;
use TVGuide\RequestHandler\Modal\ChannelGroupCreate;
use TVGuide\RequestHandler\Modal\ChannelGroupEdit;
use TVGuide\RequestHandler\Modal\ProgramInfo;

final readonly class ModalRoute implements RequestHandler
{
    public function __construct(private DbLoader $db)
    {
    }

    public function handle(Request $request): Response
    {
        return (new RegexMatch([
            'program-info$' => fn() => new ProgramInfo($this->db),
            'channel-group/' => fn() => new RegexMatch([
                'create$' => fn() => new ChannelGroupCreate($this->db),
                'edit$' => fn() => new ChannelGroupEdit($this->db),
            ]),

        ]))->handle($request);
    }
}