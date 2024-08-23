<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Viacom;

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
use function curl_getinfo;
use function curl_init;
use function simplexml_load_string;

use const CURLINFO_RESPONSE_CODE;
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

        if ($this->cfg->viacomApiUrl() === '') {
            $this->logger->info("Source disabled...");
            return;
        }

        foreach ($this->cfg->viacomChannels() as $channelId => $channelLanguage) {
            // Download EPG data for channel
            $date = new DateTimeImmutable();
            $maxDate = $date->add(new DateInterval('P3M'));
            while (true) {
                $this->logger->info("Downloading data for '{$channelId}', date '{$date->format('Y-m-d')}'");

                $url =
                    "{$this->cfg->viacomApiUrl()}{$channelId}/xmltvlegal/{$channelLanguage}/" .
                    "{$date->format('Ymd')}.xml";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

                if ($response === false) {
                    $this->logger->warning("Failed to download '{$url}' ({$status}): curl returned false");
                    break;
                }

                if ($status !== 200) {
                    if ($status === 404) {
                        $limitDate = (new DateTimeImmutable())->add(new DateInterval('P7D'));
                        if ($date->getTimestamp() > $limitDate->getTimestamp()) {
                            // If we successfully fetched a week, continue to the next channel
                            $this->logger->info("No data for date, continuing...");
                            break;
                        }

                        $this->logger->warning("Failed to download '{$url}' ({$status}): {$response}");
                        break;
                    }

                    $this->logger->warning("Failed to download '{$url}' ({$status}): {$response}");
                    continue;
                }

                $xml = simplexml_load_string(data: (string)$response, options: LIBXML_BIGLINES | LIBXML_COMPACT);
                if ($xml === false) {
                    $this->logger->warning("Invalid XML received from '{$url}'");
                    break;
                }

                $this->logger->info("Importing file...");
                try {
                    $parsedXml = new XmlParser($this->logger, $xml);
                    $programs = $parsedXml->programs();
                } catch (Throwable $e) {
                    $this->logger->warning("Failed to parse file from '{$url}: {$e->getMessage()}");
                    continue;
                }
                $this->newProgramCount += count($programs);

                // Store channel if it does not exist
                if (!array_key_exists("viacom.{$channelId}", $channels)) {
                    $this->logger->info("Adding new channel: viacom.{$channelId}");
                    $channels["viacom.{$channelId}"] = $channelRepo->add(
                        new ChannelModel(
                            originId: "viacom.{$channelId}", name: $parsedXml->channelName(),
                        )
                    );
                    ++$this->newChannelCount;
                }

                // Delete obsolete data
                $programRepo->deleteByChannelAndTimeInterval(
                    $channels["viacom.{$channelId}"],
                    $parsedXml->startTime(),
                    $parsedXml->endTime()
                );

                $programRepo->addFromImport($channels["viacom.{$channelId}"], ...$programs);

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