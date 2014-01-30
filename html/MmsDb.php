<?php
class MmsDb extends SQLite3
{
    function __construct()
    {
        $this->open(dirname(__FILE__) . '/../db/mms.db');

        $this->tryInit();
    }

    function tryInit()
    {
        $query = <<<EOF
CREATE TABLE IF NOT EXISTS `media` (
    `id` varchar(100) NOT NULL PRIMARY KEY,
    `mcode` char(6) NOT NULL,
    `version` varchar(100) NOT NULL DEFAULT '0',
    `size` integer DEFAULT NULL,
    `user_id` varchar(100) NOT NULL,
    `job_id` integer DEFAULT NULL,
    `job_state` char(1) DEFAULT NULL,
    `encoded_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL
);
EOF;
        $this->exec($query);

        $query = <<<EOF
CREATE TABLE IF NOT EXISTS `task` (
    `media_id` varchar(100) NOT NULL,
    `name` integer DEFAULT NULL,
    `url` varchar(255) DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL
);
EOF;
        $this->exec($query);
    }
}
?>