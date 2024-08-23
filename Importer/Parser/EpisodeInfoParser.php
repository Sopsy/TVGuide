<?php
declare(strict_types=1);

namespace TVGuide\Importer\Parser;

use function count;
use function preg_match;

final class EpisodeInfoParser
{
    private int $season = 0;
    private int $episode = 0;
    private int $episodeCount = 0;

    public function __construct(string $description)
    {
        preg_match('#(\d+)/(\d+)[., ]?#', $description, $matches);
        if (count($matches) === 3) {
            $this->episode = (int)$matches[1];
            $this->episodeCount = (int)$matches[2];
        }

        preg_match('#(?>osa|jakso) (\d+)#i', $description, $matches);
        if (count($matches) === 2) {
            $this->episode = (int)$matches[1];
        }

        preg_match('#(\d+)[., ]? kausi#i', $description, $matches);
        if (count($matches) === 2) {
            $this->season = (int)$matches[1];
        }

        preg_match('#(?>jakso |osa )(\d+)(?>/(\d+)[., ]?)#i', $description, $matches);
        if (count($matches) === 3) {
            $this->episode = (int)$matches[1];
            $this->episodeCount = (int)$matches[2];
        }

        preg_match('#kausi (\d+)[,.] (?>jakso |osa )?(\d+)(?>/(\d+))?[., ]?#i', $description, $matches);
        if (count($matches) >= 3) {
            $this->season = (int)$matches[1];
            $this->episode = (int)$matches[2];
            if (count($matches) >= 4) {
                $this->episodeCount = (int)$matches[3];
            }
        }
    }

    public function season(): int
    {
        return $this->season;
    }

    public function episode(): int
    {
        return $this->episode;
    }

    public function episodeCount(): int
    {
        return $this->episodeCount;
    }
}