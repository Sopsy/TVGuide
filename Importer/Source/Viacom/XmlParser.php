<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Viacom;

use DateTimeImmutable;
use DateTimeZone;
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
    private string $channelName;

    public function __construct(
        private Logger $logger,
        private SimpleXMLElement $epg
    ) {
        $channel = $this->epg->channel;
        $this->channelName = (string)$channel->{'display-name'};

        $startTime = $this->epg->xpath('//programme[1]/@air_time_start');
        $endTime = $this->epg->xpath('//programme[last()]/@air_time_end');

        if (
            $startTime === false || $endTime === false ||
            $startTime === null || $endTime === null ||
            count($startTime) !== 1 || count($endTime) !== 1
        ) {
            throw new InvalidArgumentException('Could not get XML start or end times', 0x41);
        }

        $this->startTime = $this->parseTime((string)$startTime[0]);
        $this->endTime = $this->parseTime((string)$endTime[0]);
    }

    public function startTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function endTime(): DateTimeImmutable
    {
        return $this->endTime;
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

        $programsXpath = $this->epg->xpath('//programme');
        if ($programsXpath === false || $programsXpath === null) {
            throw new InvalidArgumentException('Could not parse programs', 0x51);
        }

        foreach ($programsXpath as $program) {
            try {
                $programs[] = $this->parseProgram($program);
            } catch (ProgramParseException $e) {
                $this->logger->warning("Invalid program: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()}");
            }
        }

        return $programs;
    }

    /**
     * @throws ProgramParseException
     */
    private function parseProgram(SimpleXMLElement $program): ImportedProgramInterface
    {
        $attributes = $program->attributes();

        if ($attributes === null) {
            throw new ProgramParseException('Could not parse program start or end times', 0x61);
        }

        $startTime = trim((string)$attributes->air_time_start);
        $endTime = trim((string)$attributes->air_time_end);

        try {
            $startTime = $this->parseTime($startTime);
            $endTime = $this->parseTime($endTime);
        } catch (InvalidArgumentException $e) {
            throw new ProgramParseException('Could not parse program start or end times', 0x62, $e);
        }

        $title = $this->parseTitle($program);
        $description = $this->parseDescription($program);
        $season = $this->parseSeason($program);
        $episode = $this->parseEpisode($program);

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
        $time = trim($time);

        if ($time === '') {
            throw new InvalidArgumentException('Time parsing failed', 0x21);
        }

        try {
            $datetime = DateTimeImmutable::createFromFormat('YmdHis O', $time);
            if ($datetime === false) {
                throw new InvalidArgumentException('Time parsing failed', 0x22);
            }

            return $datetime->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception $e) {
            throw new InvalidArgumentException('Time parsing failed', 0x23, $e);
        }
    }

    /**
     * @throws ProgramParseException
     */
    private function parseTitle(SimpleXMLElement $program): string
    {
        $title = $program->title;
        $subTitle = $program->{'sub-title'};

        if ($title === null || count($title) !== 1) {
            throw new ProgramParseException('Could not parse title', 0x71);
        }

        $title = trim((string)$title);
        if ($subTitle !== null && count($subTitle) === 1) {
            $subTitle = trim((string)$subTitle);
            if ($subTitle !== '') {
                $title .= ": {$subTitle}";
            }
        }

        return $title;
    }

    private function parseDescription(SimpleXMLElement $program): string
    {
        $description = $program->desc;
        if ($description === null || count($description) !== 1) {
            $description = $program->desc_short;
            if ($description === null || count($description) !== 1) {
                $description = '';
            } else {
                $description = trim((string)$description);
            }
        } else {
            $description = trim((string)$description);
        }

        $formatDescription = $program->format_desc;
        if ($formatDescription === null || count($formatDescription) === 0) {
            $formatDescription = $program->format_desc_short;
        }

        if ($formatDescription !== null && count($formatDescription) === 1) {
            $formatDescription = trim((string)$formatDescription);
            if ($formatDescription !== '') {
                $description .= "\n\n{$formatDescription}";
            }
        }

        return $description;
    }

    private function parseSeason(SimpleXMLElement $program): int
    {
        $season = $program->{'season-num'};

        if ($season === null || count($season) !== 1) {
            return 0;
        }

        return (int)trim((string)$season);
    }

    private function parseEpisode(SimpleXMLElement $program): int
    {
        $episode = $program->{'episode-num'};

        if ($episode === null || count($episode) !== 1) {
            return 0;
        }

        return (int)trim((string)$episode);
    }
}