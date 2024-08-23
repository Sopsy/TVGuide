<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Eurosport;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use JsonException;
use SimpleXMLElement;
use Library\Logger\Contract\Logger;
use TVGuide\Importer\Contract\ImportedProgram as ImportedProgramInterface;
use TVGuide\Importer\Exception\ProgramParseException;
use TVGuide\Importer\Model\ImportedProgram;

use function count;
use function explode;
use function str_starts_with;
use function trim;

use const LIBXML_BIGLINES;
use const LIBXML_COMPACT;

final readonly class XmlParser
{
    private SimpleXMLElement $epg;
    private DateTimeImmutable $startTime;
    private DateTimeImmutable $endTime;

    public function __construct(
        private Logger $logger,
        private string $filename
    ) {
        try {
            $this->epg = new SimpleXMLElement($this->filename, LIBXML_BIGLINES | LIBXML_COMPACT, true);
        } catch (Exception | JsonException $e) {
            throw new InvalidArgumentException("Could not parse the XML file", 0x10, $e);
        }

        $dates = $this->epg->BroadcastDate_GMT;
        if ($dates === null || $dates->count() === 0) {
            throw new InvalidArgumentException("BroadcastDate_GMT missing", 0x20);
        }

        $startDate = $this->epg->xpath('//BroadcastDate_GMT[1]');
        $endDate = $this->epg->xpath('//BroadcastDate_GMT[last()]');

        if ($startDate === null || $endDate === null) {
            throw new InvalidArgumentException("BroadcastDate_GMT invalid", 0x21);
        }

        $this->startTime = $this->parseDate($startDate[0], '00:00:00');
        $this->endTime = $this->parseDate($endDate[0], '23:59:59');
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

        foreach ($this->epg->BroadcastDate_GMT ?? [] as $dateItem) {
            $date = $this->parseDate($dateItem, '00:00:00');

            foreach ($dateItem->Emission ?? [] as $programItem) {
                try {
                    $programs[] = $this->parseProgram($date, $programItem);
                } catch (ProgramParseException $e) {
                    $this->logger->warning("Invalid program in '{$this->filename}': {$e->getMessage()} ({$e->getFile()}:{$e->getLine()}");
                }

            }
        }

        return $programs;
    }

    /**
     * @throws ProgramParseException
     */
    private function parseProgram(DateTimeImmutable $date, SimpleXMLElement $programItem): ImportedProgramInterface
    {
        try {
            $startTime = $this->parseTime($date, (string)$programItem->StartTimeGMT);
            $endTime = $this->parseTime($date, (string)$programItem->EndTimeGMT);
        } catch (InvalidArgumentException $e) {
            throw new ProgramParseException('Could not parse program start or end times', 0x60, $e);
        }

        $title = $this->parseTitle($programItem);
        $description = $this->parseDescription($programItem);

        return new ImportedProgram(
            title: $title, description: $description, startTime: $startTime, endTime: $endTime,
        );
    }

    /**
     * @throws ProgramParseException
     */
    private function parseTitle(SimpleXMLElement $emission): string
    {
        $sport = $emission->Sport;
        $title = $emission->Title;

        if ($sport === null || $sport->count() === 0) {
            $sport = '';
        }

        if ($title === null || $title->count() === 0) {
            throw new ProgramParseException('Program title not found', 0x50);
        }

        $sport = trim((string)$sport);
        $title = trim((string)$title);
        if (!str_starts_with($title, $sport)) {
            $title = "{$sport}: {$title}";
        }

        return $title;
    }

    private function parseDescription(SimpleXMLElement $emission): string
    {
        $description = $emission->Feature;
        $firstBroadcast = $emission->DateFirstBroadcast;
        if ($description === null || $description->count() !== 1) {
            return '';
        }

        $description = trim((string)$description);
        if ($firstBroadcast !== null && $firstBroadcast->count() === 1) {
            $description .= " ({$firstBroadcast})";
        }

        return $description;
    }

    private function parseDate(SimpleXMLElement $element, string $time): DateTimeImmutable
    {
        $attributes = $element->attributes();
        if ($attributes === null) {
            $date = '';
        } else {
            $date = (string)$attributes['Day'];
        }

        if ($date === '') {
            throw new InvalidArgumentException('Date parsing failed', 0x20);
        }

        $date = explode('/', $date);
        if (count($date) !== 3) {
            throw new InvalidArgumentException('Date parsing failed', 0x21);
        }

        $date = "{$date[2]}-{$date[1]}-{$date[0]}";

        try {
            return new DateTimeImmutable("{$date}Z{$time}");
        } catch (Exception $e) {
            throw new InvalidArgumentException('Date parsing failed', 0x22, $e);
        }
    }

    private function parseTime(DateTimeImmutable $date, string $time): DateTimeImmutable
    {
        if (substr_count($time, ':') !== 1) {
            throw new InvalidArgumentException('Time parsing failed', 0x40);
        }

        [$hours, $minutes] = explode(':', $time);

        return $date->setTime((int)$hours, (int)$minutes);
    }
}