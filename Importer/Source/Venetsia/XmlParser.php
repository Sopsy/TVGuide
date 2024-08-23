<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Venetsia;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SimpleXMLElement;
use Library\Logger\Contract\Logger;
use TVGuide\Importer\Contract\ImportedProgram as ImportedProgramInterface;
use TVGuide\Importer\Exception\ProgramIgnoredException;
use TVGuide\Importer\Exception\ProgramParseException;
use TVGuide\Importer\Model\ImportedProgram;
use TVGuide\Importer\Parser\EpisodeInfoParser;

use function count;
use function explode;
use function preg_replace;
use function strtotime;
use function trim;

use const LIBXML_BIGLINES;
use const LIBXML_COMPACT;

final readonly class XmlParser
{
    private SimpleXMLElement $table;
    private DateTimeImmutable $startTime;
    private DateTimeImmutable $endTime;
    private string $channelId;
    private string $channelName;

    public function __construct(
        private Logger $logger,
        private string $filename
    ) {
        try {
            $xml = new SimpleXMLElement($this->filename, LIBXML_BIGLINES | LIBXML_COMPACT, true);
        } catch (Exception | JsonException $e) {
            throw new InvalidArgumentException("Could not parse the XML file", 0x10, $e);
        }

        $xml->registerXPathNamespace('tva', 'urn:tva:metadata:2002');

        if ($xml->ProgramTable === null || $xml->ProgramTable->count() !== 1) {
            throw new InvalidArgumentException("XML file does not contain a ProgramTable", 0x11);
        }

        /** @var SimpleXMLElement $table - Psalm does not understand this */
        $table = $xml->ProgramTable;
        $this->table = $table;

        if ($table->ProgramTableInformation === null || $table->ProgramTableInformation->count() !== 1) {
            throw new InvalidArgumentException("StartDate missing in ProgramTableInformation", 0x20);
        }

        /** @var SimpleXMLElement $tableInfo - Psalm does not understand this */
        $tableInfo = $table->ProgramTableInformation;

        if (!isset($this->table->ProgramTableInformation->Station)) {
            throw new InvalidArgumentException('Station element not found in ProgramTableInformation', 0x40);
        }

        $attributes = $this->table->ProgramTableInformation->Station->attributes();
        if ($attributes === null || !isset($attributes['serviceId'])) {
            throw new InvalidArgumentException('serviceId -attribute not found in Station', 0x41);
        }

        $channelName = $this->table->ProgramTableInformation->Station->xpath('.//tva:Name');
        if ($channelName === false || !isset($channelName[0])) {
            throw new InvalidArgumentException('Channel name not found', 0x42);
        }

        $this->channelId = trim((string)$attributes['serviceId']);
        $this->channelName = trim((string)$channelName[0]);

        $startDate = $tableInfo->StartDate;
        if ($startDate === null || $startDate->count() !== 1) {
            throw new InvalidArgumentException("StartDate missing in ProgramTableInformation", 0x20);
        }

        $this->startTime = $this->dateTimeToUtc((string)$startDate);

        $endDate = $tableInfo->EndDate;
        if ($endDate === null || $endDate->count() !== 1) {
            throw new InvalidArgumentException("EndDate missing in ProgramTableInformation", 0x20);
        }

        $this->endTime = $this->dateTimeToUtc((string)$endDate);
    }

    public function channelId(): string
    {
        return "venetsia.{$this->channelId}";
    }

    public function channelName(): string
    {
        return $this->channelName;
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
        /** @var array<SimpleXMLElement> $programItems */
        $programItems = $this->table->ProgramItem;
        foreach ($programItems as $programItem) {
            $next = $programItem->xpath('following-sibling::*[1]');
            if ($next === null || count($next) === 0) {
                $next = false;
            }

            try {
                if ($next !== false) {
                    $next = $this->parseProgramItem($next[0], false);
                }
                $programs[] = $this->parseProgramItem($programItem, $next);
            } catch (ProgramParseException $e) {
                $this->logger->warning("Invalid program in '{$this->filename}': {$e->getMessage()} ({$e->getFile()}:{$e->getLine()}");
            } catch (ProgramIgnoredException $e) {
                $this->logger->info("Ignoring program: {$e->getMessage()}");
            }
        }

        return $programs;
    }

    /**
     * @param SimpleXMLElement $program
     * @param ImportedProgramInterface|false $next The program that follows this program, used in end time calculation
     *     for overlaps
     * @return ImportedProgramInterface
     * @throws ProgramParseException
     * @throws ProgramIgnoredException
     */
    private function parseProgramItem(
        SimpleXMLElement $program,
        ImportedProgramInterface|false $next
    ): ImportedProgramInterface {
        $programInfo = $program->ProgramInformation->{'tva.ProgramDescription'}?->children('tva', true);

        if ($programInfo === null) {
            throw new ProgramParseException('No program info found', 0x54);
        }

        $title = $programInfo->ProgramInformationTable?->ProgramInformation?->BasicDescription?->Title;
        $description = $programInfo->ProgramInformationTable?->ProgramInformation?->BasicDescription?->Synopsis;

        $title = $title === null ? '' : $this->parseTitle($title);
        $description = $description === null ? '' : $this->parseDescription($description);

        // Ignore YLE TEEMA and YLE FEM "programs", they are not really programs at all
        if (
            $this->channelId === 'fsd' &&
            ($title === 'YLE TEEMA' || $title === 'YLE FEM') &&
            $description === ''
        ) {
            throw new ProgramIgnoredException("Non-program: {$title}", 0x50);
        }

        $startTime = $programInfo->ProgramLocationTable?->BroadcastEvent?->PublishedStartTime;
        $endTime = $programInfo->ProgramLocationTable?->BroadcastEvent?->PublishedEndTime;

        if ($startTime === null || $startTime->count() === 0) {
            throw new ProgramParseException('Start time for program not found', 0x51);
        }
        if ($endTime === null || $endTime->count() === 0) {
            throw new ProgramParseException('End time for program not found', 0x52);
        }

        $startTime = $this->dateTimeToUtc(trim((string)$startTime));
        $endTime = $this->fixedEndTime($startTime, trim((string)$endTime));

        // Fix program duration too long
        if ($next !== false && $endTime->getTimestamp() > $next->startTime()->getTimestamp()) {
            $this->logger->info("Program '{$title}' ends after the next one starts.");
            $endTime = $next->startTime();
        }

        // Fix program start/end time before/after XML metadata start/end time
        if ($startTime->getTimestamp() < $this->startTime->getTimestamp()) {
            $this->logger->info("Program '{$title}' begins before the XML metadata start time.");
            $startTime = $this->startTime;
        }
        if ($endTime->getTimestamp() > $this->endTime->getTimestamp()) {
            $this->logger->info("Program '{$title}' ends after the XML metadata end time.");
            $endTime = $this->endTime;
        }

        // Ignore duplicated programs
        // This could possibly cause wrong end time if the current one is the correct one, but whatever.
        if (
            $next !== false &&
            $title === $next->title() &&
            $description === $next->description() &&
            $startTime->getTimestamp() === $next->startTime()->getTimestamp()
        ) {
            $this->logger->info("Program '{$title}' is a duplicate of the next one.");
            throw new ProgramIgnoredException("Duplicated program: {$title}", 0x53);
        }

        $episodeInfo = new EpisodeInfoParser($description);

        return new ImportedProgram(
            title: $title,
            description: $description,
            startTime: $startTime,
            endTime: $endTime,
            season: $episodeInfo->season(),
            episode: $episodeInfo->episode(),
            episodeCount: $episodeInfo->episodeCount(),
        );
    }

    /**
     * @throws ProgramParseException
     */
    private function parseTitle(SimpleXMLElement $title): string
    {
        if ($title->count() === 0) {
            throw new ProgramParseException('Program title not found', 0x50);
        }

        // Venetsia data has a "PEGI" rating appended to most titles, we don't want it
        return preg_replace('#( \([S0-9]{1,2}\))?$#', '', trim((string)$title));
    }

    private function parseDescription(SimpleXMLElement $description): string
    {
        if ($description->count() === 1) {
            return trim((string)$description);
        }

        return '';
    }

    /**
     * Quite often end time in Venetsia data precedes start time. I don't understand how this is even possible.
     * When this happens we forget about the date they give us and just use the given hours with the start date.
     *
     * @param DateTimeImmutable $startDate
     * @param string $endDate
     * @return DateTimeImmutable Fixed end time
     */
    private function fixedEndTime(DateTimeImmutable $startDate, string $endDate): DateTimeImmutable
    {
        $time = $this->dateTimeToUtc($endDate);

        if ($time->getTimestamp() >= $startDate->getTimestamp()) {
            return $time;
        }

        $startTime = $startDate->format('H:i:s');
        $endTime = $time->format('H:i:s');

        $endTimestamp = strtotime("1970-01-01Z{$endTime}");
        $startTimestamp = strtotime("1970-01-01Z{$startTime}");
        if ($endTimestamp === false || $startTimestamp === false) {
            throw new InvalidArgumentException('Failed to parse end time', 0x60);
        }

        if ($endTimestamp > $startTimestamp) {
            // End time is after start time, assume end time is during the same day
            $newEndDate = $startDate->format('Y-m-d');
        } else {
            // End time is before start time, assume end time is on the next day
            $newEndDate = $startDate->add(new DateInterval('P1D'))->format('Y-m-d');
        }

        // See note in dateTimeToUtc docblock to understand why we hardcode tz +02:00
        return $this->dateTimeToUtc("{$newEndDate}T{$endTime}+02:00");
    }

    /**
     * NOTE:
     * Venetsia data timestamps have a wrong timezone during DST (+02:00 instead of +03:00)!
     * We ignore that and assume Europe/Helsinki is the correct timezone.
     *
     * @param string $time
     * @return DateTimeImmutable
     */
    private function dateTimeToUtc(string $time): DateTimeImmutable
    {
        $time = explode('+', $time, 2)[0];

        try {
            return (new DateTimeImmutable($time, new DateTimeZone('Europe/Helsinki')))->setTimezone(
                new DateTimeZone('UTC')
            );
        } catch (Exception $e) {
            throw new RuntimeException('Could not parse Venetsia XML dates', 4, $e);
        }
    }
}