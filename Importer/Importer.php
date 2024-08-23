<?php
declare(strict_types=1);

namespace TVGuide\Importer;

use Library\DbLoader\DbLoader;
use Library\Logger\Contract\Logger;
use TVGuide\Config\Contract\TVGuideConfig;
use TVGuide\Importer\Contract\Importer as ImporterInterface;
use TVGuide\Importer\Source\Clipsource\Importer as ClipsourceImporter;
use TVGuide\Importer\Source\Eurosport\Importer as EurosportImporter;
use TVGuide\Importer\Source\GlobalListings\Importer as GlobalListingsImporter;
use TVGuide\Importer\Source\PawaDiscovery\Importer as PawaImporter;
use TVGuide\Importer\Source\Venetsia\Importer as VenetsiaImporter;
use TVGuide\Importer\Source\Viacom\Importer as ViacomImporter;

final class Importer implements ImporterInterface
{
    private int $newChannelCount = 0;
    private int $newProgramCount = 0;

    public function __construct(
        private readonly Logger $logger,
        private readonly TVGuideConfig $cfg,
        private readonly DbLoader $db
    ) {
    }

    public function import(): void
    {
        $this->logger->info("Running TVGuide importers...");

        foreach ($this->getImporters() as $name => $importer) {
            $this->logger->info("Importing data from {$name}...");
            $importer->import();

            $this->newChannelCount += $importer->newChannelCount();
            $this->newProgramCount += $importer->newProgramCount();

            $this->logger->info("OK: {$importer->newChannelCount()} new channels, {$importer->newProgramCount()} programs imported");
        }

        $this->logger->info("All OK! New channels: {$this->newChannelCount()}, new programs: {$this->newProgramCount()}");
    }

    /**
     * @return array<string, ImporterInterface>
     */
    private function getImporters(): array
    {
        return [
            'PawaDiscovery' => new PawaImporter($this->logger, $this->cfg, $this->db),
            'Viacom' => new ViacomImporter($this->logger, $this->cfg, $this->db),
            'Clipsource' => new ClipsourceImporter($this->logger, $this->cfg, $this->db),
            'Global Listings' => new GlobalListingsImporter($this->logger, $this->cfg, $this->db),
            'Venetsia' => new VenetsiaImporter($this->logger, $this->cfg, $this->db),
            'Eurosport' => new EurosportImporter($this->logger, $this->cfg, $this->db),
        ];
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
