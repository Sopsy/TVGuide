<?php
declare(strict_types=1);

namespace TVGuide\ChannelGroup\Repository;

use JsonException;
use PDO;
use RuntimeException;
use stdClass;
use Library\DbLoader\DbLoader;
use Library\Text\ToUrlSafe;
use TVGuide\Channel\Model\Channel as ChannelModel;
use TVGuide\ChannelGroup\Contract\ChannelGroup as ChannelGroupInterface;
use TVGuide\ChannelGroup\Contract\ChannelGroupWithChannels as ChannelGroupWithChannelsInterface;
use TVGuide\ChannelGroup\Exception\ChannelGroupNotFound;
use TVGuide\ChannelGroup\Model\ChannelGroup as ChannelGroupModel;
use TVGuide\ChannelGroup\Model\ChannelGroupWithChannels;
use TVGuide\User\Contract\User;

use function array_map;
use function count;
use function json_decode;
use function rtrim;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

final readonly class ChannelGroup
{
    private PDO $db;

    public function __construct(DbLoader $dbLoader)
    {
        $this->db = $dbLoader->connection();
    }

    public function defaultSlug(): string
    {
        $q = $this->db->query('SELECT slug FROM tvguide_channel_group WHERE is_default = 1
            ORDER BY position ASC LIMIT 1');

        if ($q->rowCount() === 0) {
            throw new RuntimeException("Default channel group does not exist");
        }

        return (string)$q->fetchColumn();
    }

    /**
     * @throws ChannelGroupNotFound
     */
    public function byId(int $id, bool $showHiddenChannels): ChannelGroupWithChannelsInterface
    {
        $q = $this->db->prepare(
            'SELECT id, name, slug, user_id, is_public, is_default, cg.position,
                (SELECT 
                    JSON_ARRAYAGG(JSON_OBJECT(
                        \'id\', id,
                        \'origin_id\', origin_id,
                        \'name\', name,
                        \'slug\', slug,
                        \'is_visible\', is_visible,
                        \'position\', position
                    ))
                    FROM (
                        SELECT c.id, c.name, c.origin_id, c.slug, c.is_visible, c.position
                        FROM tvguide_channel c  
                        LEFT JOIN tvguide_channel_group_channel cgc ON cgc.channel_id = c.id
                        WHERE cgc.channel_group_id = cg.id' . ($showHiddenChannels ? '' : ' AND c.is_visible = 1') . '
                        ORDER BY c.position ASC
                    ) AS js
                ) AS channels
            FROM tvguide_channel_group cg
            LEFT JOIN tvguide_channel_group_channel cgc ON cgc.channel_group_id = cg.id
            WHERE id = :id
            ORDER BY cg.position ASC'
        );

        $q->bindValue(':id', $id, PDO::PARAM_INT);
        $q->execute();

        if ($q->rowCount() === 0) {
            throw new ChannelGroupNotFound("Channel group not found");
        }

        return $this->objectFromDbRow($q->fetchObject());
    }

    public function add(string $name, int $userId, bool $isPublic): ChannelGroupInterface
    {
        $slug = (new ToUrlSafe($name))->string();

        $q = $this->db->prepare(
            'INSERT INTO tvguide_channel_group (name, slug, user_id, is_public)
            VALUES (:name, :slug, :user_id, :is_public)'
        );

        $q->bindValue(':name', $name);
        $q->bindValue(':slug', $slug);
        $q->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $q->bindValue(':is_public', $isPublic, PDO::PARAM_BOOL);
        $q->execute();

        return new ChannelGroupModel(
            (int)$this->db->lastInsertId(),
            $name,
            $slug,
            $userId,
            $isPublic,
            false,
            255
        );
    }

    public function setChannels(ChannelGroupInterface $channelGroup, int ...$channels): void
    {
        $channels = array_map('\intval', $channels);
        $placeholders = rtrim(str_repeat('(?, ?),', count($channels)), ',');

        $this->db->beginTransaction();

        $q = $this->db->prepare('DELETE FROM tvguide_channel_group_channel
            WHERE channel_group_id = :channel_group_id');
        $q->bindValue(':channel_group_id', $channelGroup->id(), PDO::PARAM_INT);
        $q->execute();

        $q = $this->db->prepare(
            'INSERT INTO tvguide_channel_group_channel (channel_group_id, channel_id)
            VALUES ' . $placeholders
        );

        $values = [];
        foreach ($channels as $channel) {
            $values[] = $channelGroup->id();
            $values[] = $channel;
        }

        $q->execute($values);

        $this->db->commit();
    }

    public function setName(ChannelGroupInterface $channelGroup, string $name): void
    {
        $q = $this->db->prepare(
            'UPDATE tvguide_channel_group
                SET name = :name, slug = :slug
                WHERE id = :id LIMIT 1'
        );

        $q->bindValue(':name', $name);
        $q->bindValue(':slug', (new ToUrlSafe($name))->string());
        $q->bindValue(':id', $channelGroup->id(), PDO::PARAM_INT);
        $q->execute();
    }

    public function setIsPublic(ChannelGroupInterface $channelGroup, bool $isPublic): void
    {
        $q = $this->db->prepare(
            'UPDATE tvguide_channel_group SET is_public = :is_public WHERE id = :id LIMIT 1'
        );

        $q->bindValue(':is_public', $isPublic, PDO::PARAM_BOOL);
        $q->bindValue(':id', $channelGroup->id(), PDO::PARAM_INT);
        $q->execute();
    }

    public function setDefault(ChannelGroupInterface $channelGroup): void
    {
        $this->db->beginTransaction();

        $this->db->query('UPDATE tvguide_channel_group SET is_default = 0 WHERE 1');
        $q = $this->db->prepare('UPDATE tvguide_channel_group SET is_default = 1 WHERE id = :id LIMIT 1');
        $q->bindValue(':id', $channelGroup->id(), PDO::PARAM_INT);
        $q->execute();

        $this->db->commit();
    }

    public function delete(ChannelGroupInterface $channelGroup): void
    {
        $q = $this->db->prepare('DELETE FROM tvguide_channel_group WHERE id = :id LIMIT 1');
        $q->bindValue(':id', $channelGroup->id(), PDO::PARAM_INT);
        $q->execute();
    }

    public function deleteUserData(User $user): void
    {
        $q = $this->db->prepare('DELETE FROM tvguide_channel_group WHERE user_id = :user_id LIMIT 1');
        $q->bindValue(':user_id', $user->id(), PDO::PARAM_INT);
        $q->execute();
    }

    /**
     * @param User $user
     * @param bool $withPublic
     * @return array<string, ChannelGroupInterface>
     */
    public function byUser(User $user, bool $withPublic): array
    {
        $q = $this->db->prepare(
            'SELECT id, name, slug, user_id, is_public, is_default, position
            FROM tvguide_channel_group 
            WHERE ' .
                ($withPublic ? 'is_public = 1 OR ' : '') .
                ($user->isAdmin() ? 'user_id IS NULL OR ' : '') . '
                user_id = :user_id
            ORDER BY is_public ASC, position ASC'
        );

        $q->bindValue(':user_id', $user->id(), PDO::PARAM_INT);
        $q->execute();

        $channelGroups = [];

        while ($row = $q->fetchObject()) {
            $channelGroups[(string)$row->slug] = new ChannelGroupModel(
                id: (int)$row->id,
                name: (string)$row->name,
                slug: (string)$row->slug,
                userId: (int)$row->user_id,
                isPublic: (bool)$row->is_public,
                isDefault: (bool)$row->is_default,
                position: (int)$row->position
            );
        }

        return $channelGroups;
    }

    public function nameExists(string $name, int $skipId = null): bool
    {
        // don't include the edited group to the check
        $idCheck = '';
        if ($skipId !== null) {
            $idCheck = ' AND id != :id';
        }

        $q = $this->db->prepare(
            'SELECT id
            FROM tvguide_channel_group 
            WHERE name = :name' . $idCheck
        );

        $q->bindValue(':name', $name);

        if ($skipId !== null) {
            $q->bindValue(':id', $skipId, PDO::PARAM_INT);
        }

        $q->execute();

        return $q->rowCount() > 0;
    }

    private function objectFromDbRow(stdClass $row): ChannelGroupWithChannelsInterface
    {
        $groupChannels = [];
        if (!empty($row->channels)) {
            try {
                /** @var array<int, array> $channels */
                $channels = json_decode((string)$row->channels, true, 512, JSON_THROW_ON_ERROR);
                foreach ($channels as $channel) {
                    $groupChannels[] = new ChannelModel(
                        (int)$channel['id'],
                        (string)$channel['origin_id'],
                        (string)$channel['name'],
                        (string)$channel['slug'],
                        (bool)$channel['is_visible'],
                        (int)$channel['position'],
                    );
                }
            } catch (JsonException $e) {
                throw new RuntimeException('JSON decode failure', 0, $e);
            }
        }

        return new ChannelGroupWithChannels(
            new ChannelGroupModel(
                id: (int)$row->id,
                name: (string)$row->name,
                slug: (string)$row->slug,
                userId: (int)$row->user_id,
                isPublic: (bool)$row->is_public,
                isDefault: (bool)$row->is_default,
                position: (int)$row->position
            ),
            ...$groupChannels
        );
    }
}