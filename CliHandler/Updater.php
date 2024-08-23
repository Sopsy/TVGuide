<?php
declare(strict_types=1);

namespace TVGuide\CliHandler;

use Config\DbConfig;
use Library\DbLoader\DbLoader;
use Library\Logger\Contract\Logger;
use Library\Cli\Contract\CliHandler;
use TVGuide\Config\Contract\TVGuideConfig;
use TVGuide\Importer\Importer;

use function is_dir;
use function mkdir;

final readonly class Updater implements CliHandler
{
    public function __construct(
        private Logger $logger,
        private TVGuideConfig $cfg
    ) {
    }

    public function handle(string ...$args): void
    {
        $this->logger->info("Updating TV guide data...");

        if (
            !is_dir($this->cfg->tempPath()) &&
            !mkdir($this->cfg->tempPath(), 0774, true) &&
            !is_dir($this->cfg->tempPath())
        ) {
            $this->logger->alert("Could not create directory '{$this->cfg->tempPath()}'!");

            return;
        }

        $db = new DbLoader(new DbConfig());
        (new Importer($this->logger, $this->cfg, $db))->import();
    }
}