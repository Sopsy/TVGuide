<?php
declare(strict_types=1);

namespace TVGuide\Importer\Contract;

interface Importer
{
    public function import(): void;

    public function newProgramCount(): int;

    public function newChannelCount(): int;
}