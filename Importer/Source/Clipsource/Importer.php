<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Clipsource;

use DateInterval;
use DateTimeImmutable;
use Throwable;
use Library\DbLoader\DbLoader;
use Library\Logger\Contract\Logger;
use TVGuide\Channel\Model\Channel as ChannelModel;
use TVGuide\Channel\Repository\Channel;
use TVGuide\Config\Contract\TVGuideConfig;
use TVGuide\Importer\Contract\Importer as ImporterInterface;
use TVGuide\Program\Repository\Program;

use function array_key_exists;
use function count;
use function file_get_contents;
use function simplexml_load_string;

use const LIBXML_BIGLINES;
use const LIBXML_COMPACT;

final class Importer implements ImporterInterface
{
    private int $newChannelCount = 0;
    private int $newProgramCount = 0;

    public function __construct(
        private readonly Logger $logger,
        private readonly TVGuideConfig $cfg,
        private readonly DbLoader $db,
    ) {
    }

    public function import(): void
    {
        $channelRepo = new Channel($this->db);
        $programRepo = new Program($this->db);
        $channels = $channelRepo->allWithOriginId();

        if ($this->cfg->clipsourceApiUrl() === '') {
            $this->logger->info("Source disabled...");
            return;
        }

        foreach ($this->cfg->clipsourceChannels() as $channelId => $channelName) {
            // Store channel if it does not exist
            if (!array_key_exists("clipsource.{$channelId}", $channels)) {
                $this->logger->info("Adding new channel: clipsource.{$channelId}");
                $channels["clipsource.{$channelId}"] = $channelRepo->add(
                    new ChannelModel(
                        originId: "clipsource.{$channelId}", name: $channelName,
                    )
                );
                ++$this->newChannelCount;
            }

            // Download EPG data for channel
            $date = new DateTimeImmutable();
            $maxDate = $date->add(new DateInterval('P3M'));
            while (true) {
                $this->logger->info("Downloading data for '{$channelId}', date '{$date->format('Y-m-d')}'");

                $url =
                    $this->cfg->clipsourceApiUrl() .
                    "?key={$this->cfg->clipsourceApiKey()}" .
                    "&channelId={$channelId}" .
                    "&date={$date->format('Y-m-d')}";

                $read = file_get_contents(filename: $url, length: 1_048_576);
                if ($read === false) {
                    $this->logger->warning("Failed to download '{$url}'");
                    break;
                }

                $xml = simplexml_load_string(data: $read, options: LIBXML_BIGLINES | LIBXML_COMPACT);
                if ($xml === false) {
                    $this->logger->warning("Invalid XML received from '{$url}'");
                    break;
                }

                if ((string)$xml->status === '404') {
                    $this->logger->info("No data for date, continuing...");
                    break;
                }
                if ((string)$xml->status !== '') {
                    $this->logger->warning("Response status: '{$xml->status}', '{$xml->message}'");
                }

                $this->logger->info("Importing file...");
                try {
                    $parsedXml = new XmlParser($this->logger, $xml);
                    $programs = $parsedXml->programs();
                } catch (Throwable $e) {
                    $this->logger->warning("Failed to parse file: {$e->getMessage()}");
                    continue;
                }
                $this->newProgramCount += count($programs);

                // Delete obsolete data
                $programRepo->deleteByChannelAndTimeInterval(
                    $channels["clipsource.{$channelId}"],
                    $parsedXml->startTime(),
                    $parsedXml->endTime()
                );

                $programRepo->addFromImport($channels["clipsource.{$channelId}"], ...$programs);

                $date = $date->add(new DateInterval('P1D'));
                if ($date > $maxDate) {
                    $this->logger->info("Reached 3 month fetch limit for channel, continuing...");
                    break;
                }
            }
        }
    }

    public function newChannelCount(): int
    {
        return $this->newChannelCount;
    }

    public function newProgramCount(): int
    {
        return $this->newProgramCount;
    }
}