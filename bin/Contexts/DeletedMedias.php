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

            if ($this->deleteOutputAssets($media['job_id'])) {
                $mediaIds4reset[] = $media['id'];
            }
        }

        $this->resetMedias($mediaIds4reset);

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * メディアのジョブ情報&タスクをリセットする
     *
     * @param array $mediaIds
     * @return boolean
     */
    private function resetMedias($mediaIds)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $count4updateTask = 0;
        $count4deleteTask = 0;
        $isReset = false;

        if (!empty($mediaIds)) {
            $this->db->beginTransaction();
            try {
                // メディアのジョブをリセット
                $query = "UPDATE media SET updated_at = datetime('now'), job_id = '', job_state = '', job_start_at = '', job_end_at = '' WHERE id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log('$query:' . $query);
                $count4updateTask = $this->db->exec($query);

                // タスク削除
                $query = "DELETE FROM task WHERE media_id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log('$query:' . $query);
                $count4deleteTask = $this->db->exec($query);

                $this->db->commit();
                $isReset = true;
            } catch (\Exception $e) {
                $this->db->rollBack();
                $this->logger->log('resetMedias throw exception. message:' . $e->getMessage());
            }
        }

        $this->logger->log('$count4updateTask: ' . $count4updateTask);
        $this->logger->log('$count4deleteTask: ' . $count4deleteTask);

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $isReset;
    }

    /**
     * ジョブのアウトプットアセットを削除する
     *
     * @param string $jobId
     * @return boolean
     */
    private function deleteOutputAssets($jobId)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $isDeleted = false;

        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

            $outputAssets = $mediaServicesWrapper->getJobOutputMediaAssets($jobId);
            $this->logger->log('$outputAssets:' . count($outputAssets));
            foreach ($outputAssets as $asset) {
                $mediaServicesWrapper->deleteAsset($asset);
            }

            $isDeleted = true;
        } catch (\Exception $e) {
            $this->logger->log('deleteOutputAssets throw exception. jobId:' . $jobId . ' message:' . $e->getMessage());
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $isDeleted;
    }
}

?>