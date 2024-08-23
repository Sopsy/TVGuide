<?php
declare(strict_types=1);

namespace TVGuide\Importer\Source\Venetsia;

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
use function count;
use function escapeshellarg;
use function exec;
use function pathinfo;
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

        if ($this->cfg->venetsiaFtpServer() === '') {
            $this->logger->info("Source disabled...");
            return;
        }

        $ftp = new FtpDownloader(
            $this->logger,
            $this->cfg->venetsiaFtpServer(),
            $this->cfg->venetsiaFtpUsername(),
            $this->cfg->venetsiaFtpPassword()
        );

        foreach (
            $ftp->downloadFolder(
                $this->cfg->tempPath(),
                $this->cfg->venetsiaDeleteSourceFiles(),
                '*.tar'
            ) as $file
        ) {
            $this->logger->info("Unpacking file '{$file}'...");
            $pathinfo = pathinfo($file);
            $tempDir = "{$pathinfo['dirname']}/{$pathinfo['filename']}/";
            mkdir($tempDir);

            exec('tar -C ' . escapeshellarg($tempDir) . ' -xvf ' . escapeshellarg($file) . ' --wildcards --no-anchored "*.xml"');
            unlink($file);

            $xmlFiles = glob("{$tempDir}*.xml");

            foreach ($xmlFiles as $xmlFile) {
                $this->logger->info("Importing file '{$xmlFile}'...");
                try {
                    $parsedXml = new XmlParser($this->logger, $xmlFile);
                    $programs = $parsedXml->programs();
                } catch (Throwable $e) {
                    $this->logger->warning("Failed to parse file: {$e->getMessage()}");
                    continue;
                }
                $this->newProgramCount += count($programs);

                // Store channel if it does not exist
                if (!array_key_exists($parsedXml->channelId(), $channels)) {
                    $this->logger->info("Adding new channel: {$parsedXml->channelName()} ({$parsedXml->channelId()})");
                    $channels[$parsedXml->channelId()] = $channelRepo->add(
                        new ChannelModel(
                            originId: $parsedXml->channelId(), name: $parsedXml->channelName()
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
                unlink($xmlFile);
            }

            rmdir($tempDir);
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