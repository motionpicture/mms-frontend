<?php
namespace Mms\Bin\Contexts;

require_once __DIR__ . '/../BaseContext.php';

/**
 * 公開期間の終了したメディアという文脈
 *
 * @package   Mms\Bin\Contexts
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class EndedMedias extends \Mms\Bin\BaseContext
{
    /**
     * __construct
     */
    function __construct($userSettings)
    {
        parent::__construct($userSettings);
    }

    public function delete()
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        // 公開終了日時の過ぎたメディアを取得
        $mediaIds = [];
        $jobIds = [];
        try {
            $where = "job_id IS NOT NULL AND job_id <> ''"
                   . " AND start_at IS NOT NULL AND end_at IS NOT NULL"
                   . " AND start_at <> '' AND end_at <> ''"
                   . " AND start_at < datetime('now', 'localtime') AND end_at < datetime('now', 'localtime')";
            $query = "SELECT id, job_id FROM media WHERE " . $where;
            $statement = $this->db->query($query);
            while ($res = $statement->fetch()) {
                $mediaIds[] = $res['id'];
                $jobIds[] = $res['job_id'];
            }
        } catch (\Exception $e) {
            $this->logger->log('selecting medias throw exception. message:' . $e->getMessage());
            return;
        }

        $this->logger->log('$mediaIds:' . print_r($mediaIds, true));
        $this->logger->log('$jobIds:' . print_r($jobIds, true));

        if ($this->deleteWithTasks($mediaIds)) {
            foreach ($jobIds as $jobId) {
                $this->deleteAssets($jobId);
            }
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * タスクと共にメディアを削除する
     *
     * @param array $mediaIds
     * @return boolean
     */
    private function deleteWithTasks($mediaIds)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $count4deleteMedia = 0;
        $count4deleteTask = 0;
        $isDeleted = false;

        if (!empty($mediaIds)) {
            $this->db->beginTransaction();
            try {
                // メディア削除
                $query = "DELETE FROM media WHERE id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log('$query:' . $query);
                $count4deleteMedia = $this->db->exec($query);

                // タスク削除
                $query = "DELETE FROM task WHERE media_id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log('$query:' . $query);
                $count4deleteTask = $this->db->exec($query);

                $this->db->commit();
                $isDeleted = true;
            } catch (\Exception $e) {
                $this->db->rollBack();
                $this->logger->log('deleteWithTasks throw exception. message:' . $e->getMessage());
            }
        }

        $this->logger->log('$count4deleteMedia: ' . $count4deleteMedia);
        $this->logger->log('$count4deleteTask: ' . $count4deleteTask);

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $isDeleted;
    }

    /**
     * ジョブに関連したアセットを削除する
     *
     * @param string $jobId
     * @return none
     */
    private function deleteAssets($jobId)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        if (is_null($jobId) || empty($jobId)) {
            return;
        }

        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

            // ジョブのアセットを取得
            $inputAssets = $mediaServicesWrapper->getJobInputMediaAssets($jobId);
            $outputAssets = $mediaServicesWrapper->getJobOutputMediaAssets($jobId);

            $this->logger->log('$inputAssets:' . print_r($inputAssets, true));
            $this->logger->log('$outputAssets:' . print_r($outputAssets, true));

            // アセット削除
            foreach ($inputAssets as $asset) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
            foreach ($outputAssets as $asset) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
        } catch (Exception $e) {
            $this->logger->log('deleteAssets throw exception. jobId:' . $jobId . ' message:' . $e->getMessage());
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>