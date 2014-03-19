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

        $this->logFile = dirname(__FILE__) . '/../log/delete_ended_medias.log';
    }

    public function delete()
    {
        $where = "start_at IS NOT NULL AND end_at IS NOT NULL AND start_at < datetime('now', 'localtime') AND end_at < datetime('now', 'localtime')";

        // 公開終了日時の過ぎたメディアを取得
        $medias = [];
        $mediaIds = [];
        try {
            $query = "SELECT id, job_id FROM media WHERE " . $where;
            $statement = $this->db->query($query);
            while ($res = $statement->fetch()) {
                $medias[] = $res;
                $mediaIds[] = $res['id'];
            }
        } catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
        }

        $this->log('$medias: ' . count($medias));

        $count4deleteMedia = 0;
        $count4deleteTask = 0;

        if (!empty($medias)) {
            $isDeleted = false;
            $this->db->beginTransaction();
            try {
                // メディア削除
                $query = "DELETE FROM media WHERE " . $where;
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
                $this->log($e->getMessage());
            }

            if ($isDeleted) {
                foreach ($medias as $media) {
                    // ジョブがあればアセットも削除
                    try {
                        if ($media['job_id']) {
                            $this->deleteAssets($media['job_id']);
                        }
                    } catch (\Exception $e) {
                        $this->log('fail in deleting assets. message: ' . $e->getMessage());
                    }
                }
            }
        }

        $this->log('$count4deleteMedia: ' . $count4deleteMedia);
        $this->log('$count4deleteTask: ' . $count4deleteTask);
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

        if (is_null($jobId)) {
            $e = new \Exception('job id are required.');
            $this->log('fail in deleting assets: ' . $e->getMessage());
            throw $e;
        }

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

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>