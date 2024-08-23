
CREATE TABLE `tvguide_channel` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `origin_id` varchar(100) NOT NULL,
    `name` varchar(100) NOT NULL,
    `slug` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `is_visible` bit(1) NOT NULL DEFAULT b'0',
    `position` tinyint unsigned NOT NULL DEFAULT '255',
    PRIMARY KEY (`id`),
    KEY `origin_id` (`origin_id`),
    UNIQUE `slug` (`name`)
);

CREATE TABLE `tvguide_program` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `title` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    `description` varchar(6000) NOT NULL,
    `start_time` timestamp NOT NULL,
    `end_time` timestamp NOT NULL,
    `channel_id` int unsigned NOT NULL,
    `season` int unsigned DEFAULT NULL,
    `episode` int unsigned DEFAULT NULL,
    `episodes` int unsigned DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `title` (`title`,`start_time`,`end_time`,`channel_id`),
    KEY `channel_id` (`channel_id`,`start_time`,`end_time`) USING BTREE,
    KEY `start_time` (`start_time`,`channel_id`) USING BTREE,
    KEY `end_time` (`end_time`,`channel_id`) USING BTREE,
    CONSTRAINT `tvguide_program_ibfk_1` FOREIGN KEY (`channel_id`) REFERENCES `tvguide_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE tvguide_channel_group (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `position` TINYINT UNSIGNED NOT NULL DEFAULT 128,
    `is_public` BIT NOT NULL DEFAULT 0,
    `is_default` BIT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE `slug` (`slug`, `user_id`),
    CONSTRAINT `tvguide_channel_group_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES user(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE tvguide_channel_group_channel (
    `channel_group_id` int unsigned NOT NULL,
    `channel_id` int unsigned,
    `position` TINYINT UNSIGNED NOT NULL DEFAULT 128,
    KEY (`position`),
    CONSTRAINT `tvguide_channel_group_channel_ibfk_1` FOREIGN KEY (`channel_group_id`) REFERENCES tvguide_channel_group(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `tvguide_channel_group_channel_ibfk_2` FOREIGN KEY (`channel_id`) REFERENCES tvguide_channel(id) ON DELETE CASCADE ON UPDATE CASCADE
);