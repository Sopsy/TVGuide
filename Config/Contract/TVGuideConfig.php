<?php
declare(strict_types=1);

namespace TVGuide\Config\Contract;

interface TVGuideConfig
{
    public function tempPath(): string;

    public function eurosportFtpServer(): string;

    public function eurosportFtpUsername(): string;

    public function eurosportFtpPassword(): string;

    /**
     * Array format: ChannelId => Filename
     *
     * @return array<string, string>
     */
    public function eurosportFiles(): array;

    public function eurosportDeleteSourceFiles(): bool;

    public function globalListingsFtpServer(): string;

    public function globalListingsFtpUsername(): string;

    public function globalListingsFtpPassword(): string;

    public function globalListingsDeleteSourceFiles(): bool;

    public function venetsiaFtpServer(): string;

    public function venetsiaFtpUsername(): string;

    public function venetsiaFtpPassword(): string;

    public function venetsiaDeleteSourceFiles(): bool;

    public function pawaDiscoveryApiUrl(): string;

    /**
     * @return array<string>
     */
    public function pawaDiscoveryFiles(): array;

    public function clipsourceApiUrl(): string;

    public function clipsourceApiKey(): string;

    /**
     * Array format: ChannelId => ChannelName
     *
     * @return array<string, string>
     */
    public function clipsourceChannels(): array;

    public function viacomApiUrl(): string;

    /**
     * Array format: ChannelId => Language
     *
     * @return array<string, string>
     */
    public function viacomChannels(): array;
}