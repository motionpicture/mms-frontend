CREATE TABLE `media` (
    `id` text NOT NULL PRIMARY KEY,
    `code` text NOT NULL,
    `mcode` text NOT NULL,
    `category_id` integer NOT NULL,
    `version` integer NOT NULL DEFAULT '0',
    `size` integer DEFAULT NULL,
    `extension` text NOT NULL,
    `user_id` text NOT NULL,
    `movie_name` text DEFAULT NULL,
    `movie_ename` text DEFAULT NULL,
    `playtime_string` text DEFAULT NULL,
    `playtime_seconds` real DEFAULT NULL,
    `job_id` text DEFAULT NULL,
    `job_state` text DEFAULT NULL,
    `job_start_at` text DEFAULT NULL,
    `job_end_at` text DEFAULT NULL,
    `start_at` text DEFAULT NULL,
    `end_at` text DEFAULT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

CREATE TABLE `task` (
    `media_id` text NOT NULL,
    `name` text NOT NULL,
    `url` text DEFAULT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

CREATE TABLE `category` (
    `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    `name` text NOT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

CREATE TABLE `user` (
    `id` text NOT NULL PRIMARY KEY,
    `name` text DEFAULT NULL,
    `email` text DEFAULT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

BEGIN;
INSERT INTO category (name, created_at, updated_at) VALUES('特報', datetime('now', 'localtime'), datetime('now', 'localtime'));
INSERT INTO category (name, created_at, updated_at) VALUES('予告編', datetime('now', 'localtime'), datetime('now', 'localtime'));
INSERT INTO category (name, created_at, updated_at) VALUES('本編', datetime('now', 'localtime'), datetime('now', 'localtime'));
INSERT INTO category (name, created_at, updated_at) VALUES('その他', datetime('now', 'localtime'), datetime('now', 'localtime'));
COMMIT;
