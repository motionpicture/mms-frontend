<?php
namespace Mms\Bin\Contexts;

require_once __DIR__ . '/../BaseContext.php';

set_time_limit(0);

/**
 * 削除されたメディアという文脈
 *
 * @package   Mms\Bin\Contexts
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class DeletedMedias extends \Mms\Bin\BaseContext
{
    /**
     * __construct
     */
    function __construct($userSettings)
    {
        parent::__construct($userSettings);
    }

    /**
     * 削除済みのメディアについて、ジョブアセットやタスクをリセットする
     *
     * @return none
     */
    public function reset()
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        $medias = [];
        $mediaIds4reset = [];

        // 削除済み、かつ、ジョブのリセットされていないメディアを取得
        try {
            $query = "SELECT id, job_id FROM media WHERE deleted_at <> '' AND job_id <> ''";
            $statement = $this->db->query($query);
            $medias = $statement->fetchAll();
        } catch (\Exception $e) {
            $this->logger->log('selecting medias throw exception. message:' . $e->getMessage());
            return;
        }

        $this->logger->log('$medias:' . count($medias));

        foreach ($medias as $media) {
            if (!$media['job_id']) {
                continue;
            }

            $this->deleteOutputAssets($media['job_id']);
            $mediaIds4reset[] = $media['id'];
        }

        $this->resetMedias($mediaIds4reset);

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>