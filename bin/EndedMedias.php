<?php
namespace Mms\Bin;

require_once('BaseContext.php');

class EndedMedias extends BaseContext
{
    /**
     * __construct
     */
    function __construct()
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/../log/bin/delete_ended_medias/delete_ended_medias_' . $this->getMode() . '_' . date('Ymd') . '.log';
    }

    public function delete()
    {
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
            $this->log('selecting medias throw exception. message:' . $e->getMessage());
            return;
        }

        $this->log('$mediaIds:' . print_r($mediaIds, true));
        $this->log('$jobIds:' . print_r($jobIds, true));

        if ($this->deleteWithTasks($mediaIds)) {
            foreach ($jobIds as $jobId) {
                $this->deleteAssets($jobId);
            }
        }
    }

    /**
     * タスクと共にメディアを削除する
     *
     * @param array $mediaIds
     * @return boolean
     */
    private function deleteWithTasks($mediaIds)
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('args: ' . print_r(func_get_args(), true));

        $count4deleteMedia = 0;
        $count4deleteTask = 0;
        $isDeleted = false;

        if (!empty($mediaIds)) {
            $this->db->beginTransaction();
            try {
                // メディア削除
                $query = "DELETE FROM media WHERE id IN ('" . implode("','", $mediaIds) . "')";
                $this->log('$query:' . $query);
                $count4deleteMedia = $this->db->exec($query);

                // タスク削除
                $query = "DELETE FROM task WHERE media_id IN ('" . implode("','", $mediaIds) . "')";
                $this->log('$query:' . $query);
                $count4deleteTask = $this->db->exec($query);

                $this->db->commit();
                $isDeleted = true;
            } catch (\Exception $e) {
                $this->db->rollBack();
                $this->log('deleteWithTasks throw exception. message:' . $e->getMessage());
            }
        }

        $this->log('$count4deleteMedia: ' . $count4deleteMedia);
        $this->log('$count4deleteTask: ' . $count4deleteTask);

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

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
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('args: ' . print_r(func_get_args(), true));

        if (is_null($jobId) || empty($jobId)) {
            return;
        }

        try {
            $mediaServicesWrapper = $this->getMediaServicesWrapper();

            // ジョブのアセットを取得
            $inputAssets = $mediaServicesWrapper->getJobInputMediaAssets($jobId);
            $outputAssets = $mediaServicesWrapper->getJobOutputMediaAssets($jobId);

            $this->log('$inputAssets:' . print_r($inputAssets, true));
            $this->log('$outputAssets:' . print_r($outputAssets, true));

            // アセット削除
            foreach ($inputAssets as $asset) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
            foreach ($outputAssets as $asset) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
        } catch (Exception $e) {
            $this->log('deleteAssets throw exception. jobId:' . $jobId . ' message:' . $e->getMessage());
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>