<?php
require_once('MmsBinActions.php');

class MmsBinUpdateDbActions extends MmsBinActions
{
    /**
     * __construct
     *
     */
    function __construct()
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/../log/update_db.log';

        // トランザクションの開始
        $this->db->exec('BEGIN DEFERRED;');

        try {
            $query = <<<EOF
ALTER TABLE media RENAME TO media_old;
CREATE TABLE `media` (
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
INSERT INTO media SELECT * FROM media_old;
DROP TABLE media_old;
EOF;

            $this->log('$query:' . $query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }

            // コミット
            $this->db->exec('COMMIT;');
        } catch (Exception $e) {
            // ロールバック
            $this->db->exec('ROLLBACK;');

            throw $e;
        }

        $this->log(date('[Y/m/d H:i:s]') . ' end process');
    }
}

new MmsBinUpdateDbActions();

?>