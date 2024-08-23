<?php
declare(strict_types=1);

namespace TVGuide\Importer;

use FTP\Connection;
use InvalidArgumentException;
use Library\Logger\Contract\Logger;

use function ftp_close;
use function ftp_connect;
use function ftp_delete;
use function ftp_get;
use function ftp_login;
use function ftp_nlist;
use function ftp_pasv;
use function str_replace;

final class FtpDownloader
{
    private Connection|false $ftp = false;

    public function __construct(
        private readonly Logger $logger,
        private readonly string $server,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    /**
     * @param string $toPath Download destination path
     * @return array<string> A list of names of downloaded files
     */
    public function downloadFolder(string $toPath, bool $deleteSourceFiles, string $fromPath): array
    {
        $ftp = $this->connect();

        /** @var string[]|false $files */
        $files = ftp_nlist($ftp, $fromPath);
        if ($files === false) {
            throw new InvalidArgumentException("FTP: Directory listing '{$fromPath}' failed", 3);
        }

        $filesToDownload = [];
        foreach ($files as $file) {
            $filesToDownload[] = str_replace('/', '-', $file);
        }

        $downloadedFiles = $this->downloadFiles($toPath, $deleteSourceFiles, ...$filesToDownload);

        $this->disconnect();

        return $downloadedFiles;
    }

    /**
     * @param string $toPath Download destination path
     * @return array<string> A list of names of downloaded files
     */
    public function downloadFiles(string $toPath, bool $deleteSourceFiles, string ...$files): array
    {
        $ftp = $this->connect();

        $downloadedFiles = [];
        foreach ($files as $key => $file) {
            $this->logger->info("FTP: Downloading '{$file}'...");

            $targetFile = str_replace('/', '-', $file);
            if (!ftp_get($ftp, "{$toPath}/{$targetFile}", $file)) {
                $this->logger->warning("FTP: Could not download file '{$file}'");
            }

            if ($deleteSourceFiles && !ftp_delete($ftp, $file)) {
                $this->logger->warning("FTP: Could not delete file '{$file}'");
            }

            $downloadedFiles[$key] = "{$toPath}/{$targetFile}";
        }

        $this->disconnect();

        return $downloadedFiles;
    }

    private function connect(): Connection
    {
        if ($this->ftp !== false) {
            return $this->ftp;
        }

        $this->logger->info("FTP: Connecting to '{$this->server}'...");
        $connection = ftp_connect($this->server);

        if ($connection === false) {
            throw new InvalidArgumentException("FTP: Connection to '{$this->server}' failed", 1);
        }

        if (!ftp_login($connection, $this->username, $this->password)) {
            throw new InvalidArgumentException("FTP: Login to '{$this->server}' failed", 2);
        }

        if (!ftp_pasv($connection, true)) {
            throw new InvalidArgumentException("FTP: Could not change to passive mode", 3);
        }

        $this->ftp = $connection;

        return $connection;
    }

    private function disconnect(): void
    {
        if ($this->ftp === false) {
            return;
        }

        ftp_close($this->ftp);
        $this->ftp = false;
    }
}