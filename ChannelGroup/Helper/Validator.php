<?php
declare(strict_types=1);

namespace TVGuide\ChannelGroup\Helper;

use Library\ExceptionHandler\Exception\PublicErrorException;

use function _;
use function count;
use function mb_strlen;
use function preg_match;

final readonly class Validator
{
    /**
     * @param string $name
     * @param int[] $channels
     */
    public function __construct(
        private string $name,
        private array $channels,
    ) {
    }

    /**
     * @throws PublicErrorException
     */
    public function validate(): void
    {
        if ($this->name === '') {
            throw new PublicErrorException(_('Channel group name can\'t be empty'), 400);
        }

        if (mb_strlen($this->name) > 20) {
            throw new PublicErrorException(_('Channel group name is too long'), 400);
        }

        if (!preg_match('/[A-Za-z\d]+/', $this->name)) {
            throw new PublicErrorException(_('Channel group name must contain at least one alphanumeric character'), 400);
        }

        if (count($this->channels) < 2) {
            throw new PublicErrorException(_('Channel group must have at least 2 channels'), 400);
        }
    }
}