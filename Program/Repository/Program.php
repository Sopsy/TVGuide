<?php
declare(strict_types=1);

namespace TVGuide\Program\Repository;

use DateTimeImmutable;
use Exception;
use PDO;
use stdClass;
use Library\DbLoader\DbLoader;
use TVGuide\Channel\Contract\Channel;
use TVGuide\Importer\Contract\ImportedProgram;
use TVGuide\Program\Contract\Program as ProgramInterface;
use TVGuide\Program\Exception\ProgramNotFound;
use TVGuide\Program\Model\Program as ProgramModel;

use function abs;
use function array_chunk;
use function count;
use function gmdate;
use function implode;
use function time;

final readonly class Program
{
    private PDO $db;

    public function __construct(DbLoader $dbLoader)
    {
        $this->db = $dbLoader->connection();
    }

    public function addFromImport(Channel $channel, ImportedProgram ...$programs): void
    {
        // This is significantly more efficient than adding every program one by one
        // But we have to chunk the array because of the MySQL prepare value limit of 65535
        // If you add more values, remember to change chunk size accordingly
        // Chunk size is counted by dividing 65536 by number of values -1
        foreach (array_chunk($programs, 8191) as $chunk) {
            $values = [];
            $programCount = count($chunk);
            for ($i = 1; $i <= $programCount; ++$i) {
                $values[] =
                    "(:title_{$i}, :channel_id_{$i}, :description_{$i}, CAST(:start_time_{$i} AS DATETIME), " .
                    "CAST(:end_time_{$i} AS DATETIME), :season_{$i}, :episode_{$i}, :episodes_{$i})";
            }
            $values = implode(',', $values);

            $q = $this->db->prepare(
                'INSERT IGNORE INTO tvguide_program
                    (title, channel_id, description, start_time, end_time, season, episode, episodes)
                    VALUES ' . $values
            );

            $i = 1;
            foreach ($chunk as $program) {
                $q->bindValue(":title_{$i}", $program->title());
                $q->bindValue(":channel_id_{$i}", $channel->id(), PDO::PARAM_INT);
                $q->bindValue(":description_{$i}", $program->description());
                $q->bindValue(":start_time_{$i}", $program->startTime()->format('c'));
                $q->bindValue(":end_time_{$i}", $program->endTime()->format('c'));
                $q->bindValue(":season_{$i}", $program->season(), PDO::PARAM_INT);
                $q->bindValue(":episode_{$i}", $program->episode(), PDO::PARAM_INT);
                $q->bindValue(":episodes_{$i}", $program->episodeCount(), PDO::PARAM_INT);
                ++$i;
            }

            $q->execute();
        }
    }

    public function deleteByChannelAndTimeInterval(
        Channel $channel,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime
    ): void {
        $q = $this->db->prepare(
            'DELETE FROM tvguide_program
            WHERE channel_id = :channel_id
            AND start_time >= CAST(:start_time AS DATETIME)
            AND start_time <= CAST(:end_time AS DATETIME)'
        );
        $q->bindValue(':channel_id', $channel->id(), PDO::PARAM_INT);
        $q->bindValue(':start_time', $startTime->format('c'));
        $q->bindValue(':end_time', $endTime->format('c'));
        $q->execute();
    }

    /**
     * @return array<DateTimeImmutable>
     * @throws Exception
     */
    public function firstAndLastDateTime(): array
    {
        $q = $this->db->query(
            'SELECT MIN(p.start_time) AS min, MAX(p.end_time) AS max
            FROM tvguide_program p
            LEFT JOIN tvguide_channel c ON c.id = p.channel_id
            LIMIT 1'
        );

        $row = $q->fetchObject();

        return [
            new DateTimeImmutable((string)($row->min ?? ('@' . time()))),
            new DateTimeImmutable((string)($row->max ?? ('@' . time()))),
        ];
    }

    /**
     * @param int $id
     * @return ProgramInterface
     * @throws Exception
     */
    public function byId(int $id): ProgramInterface
    {
        $q = $this->db->prepare(
            'SELECT id, title, description, start_time, end_time, channel_id, season, episode, episodes
            FROM tvguide_program
            WHERE id = :id'
        );
        $q->bindValue(':id', $id);
        $q->execute();

        if ($q->rowCount() === 0) {
            throw new ProgramNotFound("Program '{$id}' not found");
        }

        return $this->fromDbRow($q->fetchObject());
    }

    /**
     * @param int $channelId
     * @param DateTimeImmutable $lastEndTime
     * @param int $limit
     * @return ProgramInterface[]
     * @throws Exception
     */
    public function programSetByChannelId(int $channelId, DateTimeImmutable $lastEndTime, int $limit = 10): array
    {
        $q = $this->db->prepare(
            'SELECT id, title, description, start_time, end_time, channel_id, season, episode, episodes
            FROM tvguide_program
            WHERE channel_id = :channel_id AND start_time >= :end_time
            LIMIT :limit'
        );

        $q->bindValue(':channel_id', $channelId, PDO::PARAM_INT);
        $q->bindValue(':end_time', $lastEndTime->format('Y-m-d H:i:s'));
        $q->bindValue(':limit', $limit, PDO::PARAM_INT);
        $q->execute();

        $programs = [];
        while ($row = $q->fetchObject()) {
            $programs[] = $this->fromDbRow($row);
        }

        return $programs;
    }

    /**
     * @param string $search
     * @param DateTimeImmutable $date
     * @param int $limit
     * @return array
     */
    public function search(string $search, DateTimeImmutable $date, int $limit): array
    {
        $tzOffset = $date->getTimezone()->getOffset($date);
        $tzString = ($tzOffset >= 0 ? '+' : '-') . gmdate("H:i", abs($tzOffset));

        $this->db->exec("SET time_zone = '{$tzString}'");

        $q = $this->db->prepare(
            'SELECT title, min(p.id) as id
            FROM tvguide_program p
            LEFT JOIN  tvguide_channel c ON c.id = p.channel_id 
            WHERE p.start_time BETWEEN :day_start AND :day_end
            AND p.title LIKE :like_search 
            GROUP BY p.title
            ORDER BY p.title ASC
            LIMIT :limit'
        );

        $q->bindValue(':day_start', $date->format('Y-m-d') . ' 00:00:00');
        $q->bindValue(':day_end', $date->format('Y-m-d') . ' 23:59:59');
        $q->bindValue(':like_search', $search . '%');
        $q->bindValue(':limit', $limit, PDO::PARAM_INT);
        $q->execute();
        $this->db->exec("SET time_zone = 'SYSTEM'");

        $results = [];
        while ($row = $q->fetchObject()) {
            $results[(int)$row->id] = (string)$row->title;
        }

        return $results;
    }

    /**
     * @param ProgramInterface $program
     * @param int $limit
     * @return ProgramInterface[]
     * @throws Exception
     */
    public function getUpcomingBroadcasts(ProgramInterface $program, int $limit = 10): array
    {
        $q = $this->db->prepare(
            '
            SELECT id, title, description, start_time, end_time, channel_id, season, episode, episodes
            FROM tvguide_program
            WHERE title = :title AND start_time > CAST(:start_time AS DATETIME)
            LIMIT :limit'
        );
        $q->bindValue(':title', $program->title());
        $q->bindValue(':start_time', (new DateTimeImmutable())->format('c'));
        $q->bindValue(':limit', $limit, PDO::PARAM_INT);
        $q->execute();

        $programs = [];
        while ($row = $q->fetchObject()) {
            $programs[] = $this->fromDbRow($row);
        }

        return $programs;
    }

    /**
     * @throws Exception
     */
    private function fromDbRow(stdClass $row): ProgramInterface
    {
        return new ProgramModel(
            (int)$row->id,
            (string)$row->title,
            (string)$row->description,
            new DateTimeImmutable((string)$row->start_time),
            new DateTimeImmutable((string)$row->end_time),
            (int)($row->season ?? 0),
            (int)($row->episode ?? 0),
            (int)$row->channel_id,
        );
    }
}