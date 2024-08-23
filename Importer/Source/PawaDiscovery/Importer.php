<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\PawaDiscovery;

use SimpleXMLElement;
use Throwable;
use Library\DbLoader\DbLoader;
use Library\Logger\Contract\Logger;
use TVGuide\Channel\Model\Channel as ChannelModel;
use TVGuide\Channel\Repository\Channel;
use TVGuide\Config\Contract\TVGuideConfig;
use TVGuide\Importer\Contract\Importer as ImporterInterface;
use TVGuide\Importer\Source\GlobalListings\XmlParser as GlobalListingsXmlParser;
use TVGuide\Program\Repository\Program;

use function array_key_exists;
use function count;
use function file_get_contents;

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

        if ($this->cfg->pawaDiscoveryApiUrl() === '') {
            $this->logger->info("Source disabled...");
            return;
        }

        foreach ($this->cfg->pawaDiscoveryFiles() as $file) {
            // Download EPG data for channel
            $url = "{$this->cfg->pawaDiscoveryApiUrl()}{$file}";

            $this->logger->info("Downloading '{$url}'...");

            $read = file_get_contents(filename: $url, length: 10_485_760);
            if ($read === false) {
                $this->logger->warning("Failed to download '{$url}'");
            }

            $this->logger->info("Importing file...");

            try {
                $xml = new SimpleXMLElement($read, LIBXML_BIGLINES | LIBXML_COMPACT, false);
            } catch (Throwable $e) {
                $this->logger->warning("Could not parse file: {$e->getMessage()}");
                continue;
            }

            try {
                // It seems the format is the same as Global Listings
                $parsedXml = new GlobalListingsXmlParser($this->logger, $xml, 'pawadiscovery');
                $programs = $parsedXml->programs();
            } catch (Throwable $e) {
                $this->logger->warning("Failed to parse file: {$e->getMessage()}");
                continue;
            }
            $this->newProgramCount += count($programs);

            // Store channel if it does not exist
            if (!array_key_exists($parsedXml->channelId(), $channels)) {
                $this->logger->info("Adding new channel: {$parsedXml->channelId()}");
                $channels[$parsedXml->channelId()] = $channelRepo->add(
                    new ChannelModel(
                        originId: $parsedXml->channelId(), name: $parsedXml->channelName(),
                    )
                );
                ++$this->newChannelCount;
            }

            // Delete obsolete data
            $programRepo->deleteByChannelAndTimeInterval(
                $channels[$parsedXml->channelId()],
                $parsedXml->startTime(),
                $parsedXml->endTime()
            );

            $programRepo->addFromImport($channels[$parsedXml->channelId()], ...$programs);
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