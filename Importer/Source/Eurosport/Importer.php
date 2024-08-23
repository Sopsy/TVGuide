<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Eurosport;

use Throwable;
use Library\DbLoader\DbLoader;
use Library\Logger\Contract\Logger;
use TVGuide\Channel\Model\Channel as ChannelModel;
use TVGuide\Channel\Repository\Channel;
use TVGuide\Config\Contract\TVGuideConfig;
use TVGuide\Importer\Contract\Importer as ImporterInterface;
use TVGuide\Importer\FtpDownloader;
use TVGuide\Program\Repository\Program;

use function array_key_exists;
use function array_keys;
use function count;
use function unlink;

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

        if ($this->cfg->eurosportFtpServer() === '') {
            $this->logger->info("Source disabled...");
            return;
        }

        $ftp = new FtpDownloader(
            $this->logger,
            $this->cfg->eurosportFtpServer(),
            $this->cfg->eurosportFtpUsername(),
            $this->cfg->eurosportFtpPassword()
        );

        foreach (array_keys($this->cfg->eurosportFiles()) as $channelId) {
            // Store channel if it does not exist
            if (!array_key_exists("eurosport.{$channelId}", $channels)) {
                $this->logger->info("Adding new channel: eurosport.{$channelId}");
                $channels["eurosport.{$channelId}"] = $channelRepo->add(
                    new ChannelModel(
                        originId: "eurosport.{$channelId}", name: "Eurosport {$channelId}",
                    )
                );
                ++$this->newChannelCount;
            }
        }

        foreach (
            $ftp->downloadFiles(
                $this->cfg->tempPath(),
                $this->cfg->eurosportDeleteSourceFiles(),
                ...$this->cfg->eurosportFiles()
            ) as $channelId => $file
        ) {
            $this->logger->info("Importing file '{$file}'...");
            try {
                $parsedXml = new XmlParser($this->logger, $file);
                $programs = $parsedXml->programs();
            } catch (Throwable $e) {
                $this->logger->warning("Failed to parse file: {$e->getMessage()}");
                continue;
            }
            $this->newProgramCount += count($programs);

            // Delete obsolete data
            $programRepo->deleteByChannelAndTimeInterval(
                $channels["eurosport.{$channelId}"],
                $parsedXml->startTime(),
                $parsedXml->endTime()
            );

            $programRepo->addFromImport($channels["eurosport.{$channelId}"], ...$programs);
            unlink($file);
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