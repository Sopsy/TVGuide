<?php
declare(strict_types=1);

namespace TVGuide;

use Config\TVGuideConfig;
use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Contract\Response;
use Library\Router\RegexMatch;
use Library\ExceptionHandler\Exception\PageNotFoundException;
use TVGuide\RequestHandler\Epg;

final readonly class Route implements RequestHandler
{
    public function __construct(
        private DbLoader $db,
    ) {
    }

    public function handle(Request $request): Response
    {
        return (new RegexMatch([
            'group/(?<group>[a-z\d-]+)/(?<date>\d{4}-\d{2}-\d{2})?$' => fn() => new Epg($this->db),
            'channel/(?<channel>[a-z\d-]+)/(?<date>\d{4}-\d{2}-\d{2})?$' => fn() => new Epg($this->db),
            '(?<date>\d{4}-\d{2}-\d{2})?$' => fn() => new Epg($this->db),
        ]))->handle($request);
    }
}