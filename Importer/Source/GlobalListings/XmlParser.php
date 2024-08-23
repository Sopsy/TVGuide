<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\GlobalListings;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use SimpleXMLElement;
use Library\Logger\Contract\Logger;
use TVGuide\Importer\Contract\ImportedProgram as ImportedProgramInterface;
use TVGuide\Importer\Exception\ProgramParseException;
use TVGuide\Importer\Model\ImportedProgram;

use function count;
use function dom_import_simplexml;
use function trim;

final readonly class XmlParser
{
    private DateTimeImmutable $startTime;
    private DateTimeImmutable $endTime;
    private string $channelId;
    private string $channelName;

    public function __construct(
        private Logger $logger,
        private SimpleXMLElement $epg,
        private string $channelIdPrefix
    ) {
        $dom = dom_import_simplexml($this->epg);

        $firstChild = $dom->firstChild;
        if ($firstChild === null) {
            throw new InvalidArgumentException('DOM element does not have any children', 0x11);
        }

        $this->channelName = trim($firstChild->textContent);

        $attributes = $this->epg->attributes();
        if ($attributes === null) {
            $channelId = '';
        } else {
            $channelId = (string)$attributes['CHANNEL_ID'];
        }

        if ($channelId === '') {
            throw new InvalidArgumentException('CHANNEL_ID attribute is missing', 0x12);
        }

        $this->channelId = trim($channelId);

        $startTime = $this->epg->xpath('//BROADCAST[1]/BROADCAST_START_DATETIME');
        $endTime = $this->epg->xpath('//BROADCAST[last()]/BROADCAST_END_TIME');

        if (
            $startTime === false || $endTime === false ||
            $startTime === null || $endTime === null ||
            count($startTime) !== 1 || count($endTime) !== 1
        ) {
            throw new InvalidArgumentException('Could not get XML start or end times', 0x30);
        }

        $this->startTime = $this->parseDateTime($startTime[0]);
        $this->endTime = $this->parseDateTime($endTime[0]);
    }

    public function startTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function endTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    public function channelId(): string
    {
        return "{$this->channelIdPrefix}.{$this->channelId}";
    }

    public function channelName(): string
    {
        return $this->channelName;
    }

    /**
     * @return ImportedProgramInterface[]
     */
    public function programs(): array
    {
        $programs = [];
        foreach ($this->epg->BROADCAST ?? [] as $broadcast) {
            try {
                $programs[] = $this->parseProgram($broadcast);
            } catch (ProgramParseException $e) {
                $this->logger->warning("Invalid program: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()}");
            }
        }

        return $programs;
    }

    /**
     * @throws ProgramParseException
     */
    private function parseProgram(SimpleXMLElement $broadcast): ImportedProgramInterface
    {
        $startDateTime = $broadcast->BROADCAST_START_DATETIME;
        $endDateTime = $broadcast->BROADCAST_END_TIME;

        if ($startDateTime === null || $endDateTime === null) {
            throw new ProgramParseException('Program start or end times missing', 0x61);
        }

        try {
            $startTime = $this->parseDateTime($startDateTime);
            $endTime = $this->parseDateTime($endDateTime);
        } catch (InvalidArgumentException $e) {
            throw new ProgramParseException('Parsing program start or end times failed', 0x60, $e);
        }

        $title = $this->parseTitle($broadcast);
        $description = $this->parseDescription($broadcast);
        $season = $this->parseSeason($broadcast);
        $episode = $this->parseEpisode($broadcast);

        return new ImportedProgram(
            title: $title,
            description: $description,
            startTime: $startTime,
            endTime: $endTime,
            season: $season,
            episode: $episode
        );
    }

    /**
     * @throws ProgramParseException
     */
    private function parseTitle(SimpleXMLElement $broadcast): string
    {
        $title = $broadcast->BROADCAST_TITLE;

        if ($title === null || $title->count() === 0) {
            throw new ProgramParseException('Program title not found', 0x50);
        }

        return trim((string)$title);
    }

    /**
     * @param SimpleXMLElement $broadcast
     * @return string
     */
    private function parseDescription(SimpleXMLElement $broadcast): string
    {
        $subTitle = $broadcast->BROADCAST_SUBTITLE;
        $description = $broadcast->PROGRAMME?->TEXT?->TEXT_TEXT;

        if ($description === null || $description->count() !== 1) {
            return '';
        }

        $description = trim((string)$description);
        if ($subTitle === null || $subTitle->count() === 1) {
            $description = trim((string)$subTitle) . ": {$description}";
        }

        return $description;
    }

    /**
     * @param SimpleXMLElement $broadcast
     * @return int
     */
    private function parseSeason(SimpleXMLElement $broadcast): int
    {
        $season = $broadcast->PROGRAMME?->SERIES_NUMBER;

        if ($season === null || $season->count() !== 1) {
            return 0;
        }

        return (int)trim((string)$season);
    }

    /**
     * @param SimpleXMLElement $broadcast
     * @return int
     */
    private function parseEpisode(SimpleXMLElement $broadcast): int
    {
        $episode = $broadcast->PROGRAMME?->EPISODE_NUMBER;

        if ($episode === null || $episode->count() !== 1) {
            return 0;
        }

        return (int)trim((string)$episode);
    }

    private function parseDateTime(SimpleXMLElement $element): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable(trim((string)$element));
        } catch (Exception $e) {
            throw new InvalidArgumentException('Date parsing failed', 0x20, $e);
        }
    }
}