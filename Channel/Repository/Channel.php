<?php
declare(strict_types=1);

namespace TVGuide\Channel\Repository;

use DateTimeImmutable;
use Exception;
use JsonException;
use PDO;
use RuntimeException;
use stdClass;
use Library\DbLoader\DbLoader;
use TVGuide\Channel\Contract\Channel as ChannelInterface;
use TVGuide\Channel\Exception\ChannelNotFound;
use TVGuide\Channel\Model\Channel as ChannelModel;
use TVGuide\Program\Model\Program as ProgramModel;

use function abs;
use function count;
use function gmdate;
use function json_decode;
use function rtrim;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final readonly class Channel
{
    private PDO $db;

    public function __construct(DbLoader $dbLoader)
    {
        $this->db = $dbLoader->connection();
    }

    public function add(ChannelInterface $channel): ChannelInterface
    {
        $q = $this->db->prepare(
            'INSERT INTO tvguide_channel
            (origin_id, name, slug, is_visible, position)
            VALUES (:origin_id, :name, :slug, :is_visible, :position)'
        );
        $q->bindValue(':origin_id', $channel->originId());
        $q->bindValue(':name', $channel->name());
        $q->bindValue(':slug', $channel->slug());
        $q->bindValue(':is_visible', $channel->isVisible(), PDO::PARAM_BOOL);
        $q->bindValue(':position', $channel->position(), PDO::PARAM_INT);
        $q->execute();

        return new ChannelModel(
            (int)$this->db->lastInsertId(),
            $channel->originId(),
            $channel->name(),
            $channel->slug(),
            $channel->isVisible(),
            $channel->position(),
            ...$channel->programs()
        );
    }

    /**
     * @throws ChannelNotFound
     */
    public function bySlug(string $slug): ChannelInterface
    {
        $q = $this->db->prepare(
            "SELECT id, origin_id, name, slug, is_visible, position
            FROM tvguide_channel WHERE slug = :slug
            ORDER BY position ASC"
        );

        $q->bindValue(':slug', $slug);
        $q->execute();

        if ($q->rowCount() === 0) {
            throw new ChannelNotFound();
        }

        return ($this->fromDbRow($q->fetchObject()));
    }

    /**
     * @param bool $onlyUpcoming
     * @param DateTimeImmutable $date
     * @param ChannelInterface ...$channels
     * @return array<int, ChannelInterface>
     */
    public function byChannelsWithPrograms(bool $onlyUpcoming, DateTimeImmutable $date, ChannelInterface ...$channels): array
    {
        if (count($channels) === 0) {
            return [];
        }

        $tzOffset = $date->getTimezone()->getOffset($date);
        $tzString = ($tzOffset >= 0 ? '+' : '-') . gmdate("H:i", abs($tzOffset));

        $this->db->exec("SET time_zone = '{$tzString}'");

        $inClause = '';
        for ($i = 0, $iMax = count($channels); $i < $iMax; $i++) {
            $inClause .= sprintf(':channel%d', $i) . ',';
        }
        $inClause = rtrim($inClause, ',');

        if ($onlyUpcoming) {
            $programQuery = '(
                (
                    SELECT p.id, p.title, p.description, p.start_time, p.end_time, p.season, p.episode, p.episodes
                    FROM tvguide_program p
                    WHERE p.channel_id = c.id
                    AND end_time < NOW() AND end_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                    ORDER BY end_time DESC
                    LIMIT 1
                )
                UNION 
                (
                    SELECT p.id, p.title, p.description, p.start_time, p.end_time, p.season, p.episode, p.episodes
                    FROM tvguide_program p
                    WHERE channel_id = c.id
                    AND end_time >= NOW()
                    ORDER BY end_time ASC
                    LIMIT 19
                )
            ) AS upcoming_programs_subquery';
        } else {
            $programQuery = 'tvguide_program p
                WHERE p.channel_id = c.id AND p.start_time >= :datetime_start AND p.start_time <= :datetime_end';
        }

        $q = $this->db->prepare(
            'SELECT c.id, c.origin_id, c.name, c.slug, c.is_visible, c.position,
            IFNULL((SELECT
                JSON_ARRAYAGG(JSON_OBJECT(
                    \'id\', id,
                    \'title\', title,
                    \'description\', description,
                    \'start_time\', start_time,
                    \'end_time\', end_time,
                    \'season\', season,
                    \'episode\', episode,
                    \'episodes\', episodes
                ))
                FROM ' . $programQuery . '
            ), \'[]\') AS programs
            FROM tvguide_channel c
            WHERE c.id IN(' . $inClause . ')
            ORDER BY position ASC'
        );

        $i = 0;
        foreach ($channels as $channel) {
            $q->bindValue(':channel' . $i, $channel->id());
            $i++;
        }

        if (!$onlyUpcoming) {
            $q->bindValue(':datetime_start', $date->format('Y-m-d') . ' 00:00:00');
            $q->bindValue(':datetime_end', $date->format('Y-m-d') . ' 23:59:59');
        }

        $q->execute();
        $this->db->exec("SET time_zone = 'SYSTEM'");

        $channels = [];
        while ($row = $q->fetchObject()) {
            $channels[(int)$row->id] = $this->fromDbRow($row, $date);
        }

        return $channels;
    }

    /**
     * @return array<int, ChannelInterface>
     */
    public function all(): array
    {
        $q = $this->db->query('
            SELECT id, origin_id, name, slug, is_visible, position
            FROM tvguide_channel
            ORDER BY name ASC');

        $channels = [];
        while ($row = $q->fetchObject()) {
            $channels[(int)$row->id] = $this->fromDbRow($row);
        }

        return $channels;
    }

    /**
     * @return array<string, ChannelInterface>
     */
    public function allWithOriginId(): array
    {
        $q = $this->db->query(
            'SELECT id, origin_id, name, slug, is_visible, position FROM tvguide_channel'
        );

        $channels = [];
        while ($row = $q->fetchObject()) {
            $channels[(string)$row->origin_id] = $this->fromDbRow($row);
        }

        return $channels;
    }

    /**
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function search(string $search, int $limit): array
    {
        $q = $this->db->prepare(
            'SELECT id, origin_id, name, slug, is_visible, position
            FROM tvguide_channel
            WHERE name LIKE :search
            LIMIT :limit'
        );

        $q->bindValue(':search', $search . "%");
        $q->bindValue(':limit', $limit, PDO::PARAM_INT);
        $q->execute();

        $channels = [];
        while ($row = $q->fetchObject()) {
            $channels[(int)$row->id] = $this->fromDbRow($row);
        }

        return $channels;
    }

    private function fromDbRow(stdClass $row, DateTimeImmutable $date = null): ChannelInterface
    {
        $channelPrograms = [];
        if (!empty($row->programs) && $date !== null) {
            try {
                /** @var array<int, array> $programs */
                $programs = json_decode((string)$row->programs, true, 512, JSON_THROW_ON_ERROR);
                foreach ($programs as $program) {
                    $channelPrograms[] = new ProgramModel(
                        (int)$program['id'],
                        (string)$program['title'],
                        (string)$program['description'],
                        new DateTimeImmutable((string)$program['start_time'], $date->getTimezone()),
                        new DateTimeImmutable((string)$program['end_time'], $date->getTimezone()),
                        (int)$program['season'],
                        (int)$program['episode'],
                        (int)$row->id,
                    );
                }
            } catch (JsonException) {
            } catch (Exception $e) {
                throw new RuntimeException('Exception caught', 0, $e);
            }
        }

        return new ChannelModel(
            (int)$row->id,
            (string)$row->origin_id,
            (string)$row->name,
            (string)$row->slug,
            (bool)$row->is_visible,
            (int)$row->position,
            ...$channelPrograms
        );
    }
}