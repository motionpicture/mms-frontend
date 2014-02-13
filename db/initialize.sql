CREATE TABLE IF NOT EXISTS `media` (
    `id` varchar(100) NOT NULL PRIMARY KEY,
    `mcode` char(6) NOT NULL,
    `category_id` integer NOT NULL,
    `version` integer NOT NULL DEFAULT '0',
    `size` integer DEFAULT NULL,
    `extension` varchar(100) NOT NULL,
    `user_id` varchar(100) NOT NULL,
    `job_id` varchar(100) DEFAULT NULL,
    `job_state` char(1) DEFAULT NULL,
    `job_start_at` datetime DEFAULT NULL,
    `job_end_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL
);

CREATE TABLE IF NOT EXISTS `task` (
    `media_id` varchar(100) NOT NULL,
    `name` varchar(100) NOT NULL,
    `url` varchar(255) DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL
);

CREATE TABLE IF NOT EXISTS `category` (
    `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    `name` varchar(100) NOT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL
);

CREATE TABLE IF NOT EXISTS `user` (
    `id` varchar(100) NOT NULL PRIMARY KEY,
    `name` varchar(100) DEFAULT NULL,
    `email` varchar(255) DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL
);

BEGIN;
INSERT INTO category (name, created_at, updated_at) VALUES('カテゴリー1', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('カテゴリー2', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('カテゴリー3', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('カテゴリー4', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('カテゴリー5', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('カテゴリー6', datetime('now'), datetime('now'));
COMMIT;
