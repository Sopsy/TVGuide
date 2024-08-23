<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Clipsource;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use SimpleXMLElement;
use Library\Logger\Contract\Logger;
use TVGuide\Importer\Contract\ImportedProgram as ImportedProgramInterface;
use TVGuide\Importer\Exception\ProgramParseException;
use TVGuide\Importer\Model\ImportedProgram;

use function count;
use function trim;

final readonly class XmlParser
{
    private DateTimeImmutable $startTime;
    private DateTimeImmutable $endTime;

    public function __construct(
        private Logger $logger,
        private SimpleXMLElement $epg
    ) {
        $this->startTime = $this->parseTime((string)$this->epg->from);
        $this->endTime = $this->parseTime((string)$this->epg->to);
    }

    public function startTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function endTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    /**
     * @return ImportedProgramInterface[]
     */
    public function programs(): array
    {
        $programs = [];

        $eventList = $this->epg->eventList;
        $contentList = $this->epg->contentList;

        foreach ($eventList->event ?? [] as $eventItem) {
            foreach ($contentList->content ?? [] as $contentItem) {
                if ((string)$eventItem->contentIdRef !== (string)$contentItem->contentId) {
                    continue;
                }

                try {
                    $programs[] = $this->parseProgram($eventItem, $contentItem);
                } catch (ProgramParseException $e) {
                    $this->logger->warning("Invalid program: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()}");
                }
            }
        }

        return $programs;
    }

    /**
     * @throws ProgramParseException
     */
    private function parseProgram(SimpleXMLElement $eventItem, SimpleXMLElement $contentItem): ImportedProgramInterface
    {
        $timeList = $eventItem->timeList;
        if ($timeList === null || count($timeList) !== 1) {
            throw new ProgramParseException('Could not parse program start or end times', 0x11);
        }

        $time = $timeList->time;
        if ($time === null || count($time) !== 1) {
            throw new ProgramParseException('Could not parse program start or end times', 0x12);
        }

        try {
            $startTime = $this->parseTime((string)$time->startTime);
            $endTime = $this->parseTime((string)$time->endTime);
        } catch (InvalidArgumentException $e) {
            throw new ProgramParseException('Could not parse program start or end times', 0x13, $e);
        }
        $title = $this->parseTitle($contentItem);
        $description = $this->parseDescription($contentItem);
        $season = $this->parseSeason($contentItem);
        $episode = $this->parseEpisode($contentItem);

        return new ImportedProgram(
            title: $title,
            description: $description,
            startTime: $startTime,
            endTime: $endTime,
            season: $season,
            episode: $episode
        );
    }

    private function parseTime(string $time): DateTimeImmutable
    {
        if ($time === '') {
            throw new InvalidArgumentException('Time parsing failed', 0x21);
        }

        try {
            return new DateTimeImmutable($time);
        } catch (Exception $e) {
            throw new InvalidArgumentException('Time parsing failed', 0x22, $e);
        }
    }

    /**
     * @throws ProgramParseException
     */
    private function parseTitle(SimpleXMLElement $contentItem): string
    {
        $titleList = $contentItem->titleList;
        if ($titleList === null || count($titleList) !== 1) {
            throw new ProgramParseException('Could not parse program title', 0x31);
        }

        $content = '';
        $series = '';
        foreach ($titleList->title ?? [] as $title) {
            $attributes = $title->attributes();
            if ($attributes === null) {
                throw new ProgramParseException('Could not parse program title', 0x32);
            }

            // Get text from the first content title item
            if ($content === '' && (string)$attributes->type === 'content') {
                $content = trim((string)$title);
            }

            // Get text from the first series title item
            if ($series === '' && (string)$attributes->type === 'series') {
                $series = trim((string)$title);
            }
        }

        if ($content !== '' && $series !== '' && $series !== $content) {
            return "{$series}: {$content}";
        }

        if ($content !== '') {
            return $content;
        }

        return $series;
    }

    private function parseDescription(SimpleXMLElement $contentItem): string
    {
        $descriptionList = $contentItem->descriptionList;
        if ($descriptionList === null || count($descriptionList) !== 1) {
            return '';
        }

        $description = '';
        foreach ($descriptionList->description ?? [] as $descriptionItem) {
            // Get text from the first description item
            if ($description === '') {
                $description = trim((string)$descriptionItem);
            }
        }

        // Append genre lists to the description
        foreach ($contentItem->genreList ?? [] as $genreList) {
            $genre = $genreList->genre;

            if ($genre === null) {
                continue;
            }

            $mainGenre = trim((string)$genre->mainGenre);
            if ($mainGenre !== '') {
                $description .= " ({$mainGenre})";
            }

            $subGenreList = $genre->subGenreList;
            if ($subGenreList === null || count($subGenreList) === 0) {
                continue;
            }

            foreach ($subGenreList->subGenre ?? [] as $subGenre) {
                $subGenre = trim((string)$subGenre);
                if ($subGenre !== '') {
                    $description .= " ({$subGenre})";
                }
            }
        }

        return $description;
    }

    /**
     * @param SimpleXMLElement $contentItem
     * @return int
     */
    private function parseSeason(SimpleXMLElement $contentItem): int
    {
        $season = $contentItem->seasonNumber;

        if ($season === null || $season->count() !== 1) {
            return 0;
        }

        return (int)trim((string)$season);
    }

    /**
     * @param SimpleXMLElement $contentItem
     * @return int
     */
    private function parseEpisode(SimpleXMLElement $contentItem): int
    {
        $episode = $contentItem->episodeNumber;

        if ($episode === null || $episode->count() !== 1) {
            return 0;
        }

        return (int)trim((string)$episode);
    }
}