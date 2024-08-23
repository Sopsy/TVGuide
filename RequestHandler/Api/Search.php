<?php
declare(strict_types=1);

namespace TVGuide\RequestHandler\Api;

use DateTimeImmutable;
use Library\DbLoader\DbLoader;
use Library\HttpMessage\Contract\Request;
use Library\HttpMessage\Contract\RequestHandler;
use Library\HttpMessage\Message\Response;
use TVGuide\Channel\Contract\Channel as ChannelInterface;
use TVGuide\Channel\Repository\Channel;
use TVGuide\Program\Repository\Program;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class Search implements RequestHandler
{
    public function __construct(private DbLoader $db) {
    }

    public function handle(Request $request): Response
    {
        $date = new DateTimeImmutable(
            $request->bodyParam('date'), $request->user()->timezone()
        );

        $search = $request->bodyParam('search');
        $limit = (int)$request->bodyParam('limit', '5');

        if ($limit > 10) {
            $limit = 10;
        }

        if ($limit < 1) {
            $limit = 1;
        }

        $channels = (new Channel($this->db))->search($search, $limit);
        $programs = (new Program($this->db))->search($search, $date, $limit);

        $response = [];
        /** @var ChannelInterface $channel */
        foreach ($channels as $channel) {
            $response['channels'][$channel->id()] = [
                'name' => $channel->name(),
                'url' => $channel->url($date),
                'slug' => $channel->slug(),
            ];
        }

        /** @var string $programTitle */
        foreach ($programs as $programId => $programTitle) {
            $response['programs'][(int)$programId] = $programTitle;
        }

        return new Response(json_encode($response, JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => ['application/json'],
        ]);
    }
}