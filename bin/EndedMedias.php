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
        try {
            $query = "SELECT * FROM media WHERE " . $where;
            $statement = $this->db->query($query);
            $medias = $statement->fetchAll();
        } catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
        }

        $this->log('$medias: ' . count($medias));

        $count4delete = 0;
        try {
            $query = "DELETE FROM media WHERE " . $where;
            $this->log('$query:' . $query);
            $count4delete = $this->db->exec($query);
        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }

        $this->log('$count4delete: ' . $count4delete);

        if ($count4delete > 0) {
            foreach ($medias as $media) {
                // ジョブがあればアセットも削除
                try {
                    if ($media['job_id']) {
                        $this->deleteAssets($media['job_id']);
                    }
                } catch (\Exception $e) {
                    $this->log($e->getMessage());
                }
            }
        }
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