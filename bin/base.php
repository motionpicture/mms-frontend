<?php
class MyDB extends SQLite3
{
    function __construct()
    {
      $this->open('../db/mms.db');

      $this->tryInit();
    }

    function tryInit()
    {
        $query = <<<EOF
CREATE TABLE IF NOT EXISTS `media` (
    `id` varchar(100) NOT NULL PRIMARY KEY,
    `mcode` char(6) NOT NULL DEFAULT '',
    `version` varchar(100) NOT NULL DEFAULT '',
    `size` integer DEFAULT NULL,
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


function debug($content) {
    echo '<pre>';
    print_r($content);
    echo '</pre>';
    return;
}

?>
