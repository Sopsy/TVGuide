<?php
declare(strict_types=1);

namespace TVGuide\Channel\Model;

use DateTimeInterface;
use Library\Text\ToUrlSafe;
use TVGuide\Channel\Contract\Channel as ChannelInterface;
use TVGuide\Program\Contract\Program;

use function dirname;
use function is_file;

final readonly class Channel implements ChannelInterface
{
    /** @var Program[] */
    private array $programs;
    private string $slug;

    public function __construct(
        private int $id = 0,
        private string $originId = '',
        private string $name = '',
        string $slug = '',
        private bool $isVisible = false,
        private int $position = 255,
        Program ...$programs
    ) {
        $this->programs = $programs;

        if ($slug === '') {
            $this->slug =  (new ToUrlSafe($this->name))->string();
        } else {
            $this->slug = $slug;
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function originId(): string
    {
        return $this->originId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function url(DateTimeInterface $date = null): string
    {
        $return = "/channel/{$this->slug}/";

        if ($date !== null) {
            $return .= $date->format('Y-m-d');
        }

        return $return;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function programs(): array
    {
        return $this->programs;
    }

    public function hasLogo(): bool
    {
        return is_file(dirname(__DIR__, 4) . "/static/img/tvlogos/{$this->slug()}.png");
    }

    public function logoUrl(): string
    {
        return "/img/tvlogos/{$this->slug()}.png";
    }
}