<?php
declare(strict_types=1);

namespace TVGuide;

use TVGuide\RequestHandler\Api\ChannelGroup\Create;
use TVGuide\RequestHandler\Api\ChannelGroup\Delete;
use TVGuide\RequestHandler\Api\ChannelGroup\Edit;
use TVGuide\RequestHandler\Api\LoadPrograms;
use TVGuide\RequestHandler\Api\Search;

final readonly class ApiRoute implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        return (new RegexMatch([
            'search$' => fn() => new Search($this->db),
            'load-programs$' => fn() => new LoadPrograms($this->db),
            'channel-group/' => fn() => new RegexMatch([
                'create$' => fn() => new Create($this->db),
                'delete$' => fn() => new Delete($this->db),
                'edit$' => fn() => new Edit($this->db),
            ]),
        ]))->handle($request);
    }
}