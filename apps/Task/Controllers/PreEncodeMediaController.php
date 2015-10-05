<?php
namespace Mms\Task\Controllers;

use \WindowsAzure\MediaServices\Models\Asset;
use \WindowsAzure\MediaServices\Models\Job;
use \WindowsAzure\MediaServices\Models\Task;
use \WindowsAzure\MediaServices\Models\TaskOptions;

set_time_limit(0);
ini_set('memory_limit', '1024M');

/**
 * メディアサービスにてエンコードを開始する前のメディアという文脈
 *
 * @package   Mms\Task\Controllers
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class PreEncodeMediaController extends BaseController
{
    /**
     * azureでアップロード済みのメディアID
     *
     * @var string
     */
    private static $mediaId = null;

    /**
     * アップロード先のアセットID
     *
     * @var string
     */
    private static $assetId = null;

    public function tryEncode()
    {
        $media = false;

        try {
            // アセット作成済み、ジョブ未登録、未削除のメディアを取得
            $query = "SELECT id, asset_id FROM media WHERE asset_id <> '' AND job_id = '' AND deleted_at = '' ORDER BY updated_at ASC LIMIT 1";
            $result = $this->db->query($query);
            $media = $result->fetch();
        } catch (\Exception $e) {
            $this->logger->log("selecting medias throw exception. message:{$e->getMessage()}");
        }

        if (!$media) {
            $this->logger->log('no medias waiting for encoding.');
            return false;
        }

        $this->logger->log("now encoding media... id:{$media['id']}");

        self::$mediaId = $media['id'];
        self::$assetId = $media['asset_id'];

        return $this->encode();
    }

    /**
     * エンコード処理を施す
     *
     * @return boolean
     */
    private function encode()
    {
        $this->logger->log('start function: ' . __FUNCTION__);

        $job = null;
        $isUpdated = false;

        try {
            $job = $this->createJob();

            if (!is_null($job)) {
                $this->updateJob($job->getId(), $job->getState());
                $isUpdated = true;
            }
        } catch (\Exception $e) {
            $message = 'encode throw exception. $mediaId:' . self::$mediaId . ' message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);
        }

        $this->logger->log('encode result:' . print_r($isUpdated, true));

        $this->logger->log('end function: ' . __FUNCTION__ );

        return $isUpdated;
    }

    /**
     * jobを作成する
     *
     * @return \WindowsAzure\MediaServices\Models\Job
     */
    private function createJob()
    {
        $this->logger->log('start function: ' . __FUNCTION__);

        $job = null;

        $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

        $tasks = $this->prepareTasks();

        $inputAsset = $mediaServicesWrapper->getAsset(self::$assetId);

        $job = new Job();
        $job->setName('job_for_' . self::$mediaId);
        $job = $mediaServicesWrapper->createJob($job, array($inputAsset), $tasks);

        $this->logger->log('job has been created. job:' . $job->getId());

        $this->logger->log('end function: ' . __FUNCTION__);

        return $job;
    }

    /**
     * ジョブにセットするタスクリストを用意する
     *
     * ジョブを作成する上で最も肝になる部分
     * 更新する場合
     * タスクの順序や、JobInputAssetとJobOutputAssetのキーナンバーに、気をつけること
     *
     *　Azure Media Services プロセッサ
     * @see http://msdn.microsoft.com/ja-jp/library/azure/dn673582.aspx
     * 
     * @see http://msdn.microsoft.com/ja-jp/library/dn619392.aspx
     * @see http://msdn.microsoft.com/ja-jp/library/dn629573.aspx
     * @return multitype:\WindowsAzure\MediaServices\Models\Task
     */
    private function prepareTasks()
    {
        $this->logger->log('start function: ' . __FUNCTION__);

        $tasks = array();
        $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

        // adaptive bitrate mp4
        $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor(\Mms\Lib\AzureContext::MEDIA_SERVICES_PROCESSOR_NAME_ENCODER);
        $taskBody = $this->getMediaServicesTaskBody(
            'JobInputAsset(0)',
            'JobOutputAsset(0)',
            Asset::OPTIONS_NONE,
            \Mms\Lib\Models\Task::toAssetName(self::$mediaId, \Mms\Lib\Models\Task::NAME_ADAPTIVE_BITRATE_MP4)
        );
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
        $task->setConfiguration('H264 Adaptive Bitrate MP4 Set 1080p');
        $tasks[] = $task;

        $this->logger->log('tasks has been prepared. tasks count:' . count($tasks));

        $this->logger->log('end function: ' . __FUNCTION__);

        return $tasks;
    }

    /**
     * ジョブにセットするタスクリストを用意する
     *
     * ジョブを作成する上で最も肝になる部分
     * 更新する場合
     * タスクの順序や、JobInputAssetとJobOutputAssetのキーナンバーに、気をつけること
     *
     * 1. ダイナミックパッケージング
     * 2. 入力ファイルを一連の複数ビットレート MP4 にエンコードする。
     * 3. 複数ビットレート MP4をスムーズストリームにパッケージする。
     * 4. スムーズ ストリームを暗号化する。
     * 5. 暗号化されたスムーズ ストリームをHLSにパッケージしてPlayReadyで暗号化されたHLSを取得する。
     *
     * @see http://msdn.microsoft.com/ja-jp/library/dn629573.aspx
     * @return multitype:\WindowsAzure\MediaServices\Models\Task
     */
    private function prepareTasks2()
    {
      $this->logger->log('start function: ' . __FUNCTION__);

      $tasks = array();
      $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

       // adaptive bitrate mp4
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor(\Mms\Lib\AzureContext::MEDIA_SERVICES_PROCESSOR_NAME_ENCODER);
      $taskBody = $this->getMediaServicesTaskBody(
          'JobInputAsset(0)',
          'JobOutputAsset(0)',
          Asset::OPTIONS_NONE,
          \Mms\Lib\Models\Task::toAssetName(self::$mediaId, \Mms\Lib\Models\Task::NAME_ADAPTIVE_BITRATE_MP4)
      );
      $this->logger->log('$taskBody: ' . $taskBody);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $task->setConfiguration('H264 Adaptive Bitrate MP4 Set 720p');
      $tasks[] = $task;

      // smooth streaming
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Packager');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(0)',
          'JobOutputAsset(1)',
          Asset::OPTIONS_NONE,
          \Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING
      );
      $this->logger->log('$taskBody: ' . $taskBody);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = __DIR__ . '/config/MediaPackager_MP4ToSmooth.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      // http_live_streaming
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Packager');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(1)',
          'JobOutputAsset(2)',
          Asset::OPTIONS_NONE,
          \Mms\Lib\Models\Task::NAME_HLS
      );
//         $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::PROTECTED_CONFIGURATION);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = __DIR__ . '/config/MediaPackager_SmoothToHLS.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      // PlayReady
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Encryptor');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(1)',
          'JobOutputAsset(3)',
          Asset::OPTIONS_COMMON_ENCRYPTION_PROTECTED,
          \Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING_PLAYREADY
      );
      $this->logger->log('$taskBody: ' . $taskBody);
      // テスト段階では、TaskOptions::PROTECTED_CONFIGURATIONだとkeyIdを設定しなさい、と怒られる
//         $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::PROTECTED_CONFIGURATION);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = __DIR__ . '/config/MediaEncryptor_PlayReadyProtection.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      // http_live_streaming_playready
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Packager');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(3)',
          'JobOutputAsset(4)',
          Asset::OPTIONS_COMMON_ENCRYPTION_PROTECTED,
          \Mms\Lib\Models\Task::NAME_HLS_PLAYREADY
      );
//         $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::PROTECTED_CONFIGURATION);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = __DIR__ . '/config/MediaPackager_SmoothToHLS.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      $this->logger->log('tasks has been prepared. tasks count: ' . count($tasks));

      $this->logger->log('end function: ' . __FUNCTION__);

      return $tasks;
    }

    /**
     * タスクボディ文字列を作成する
     *
     * @param string $inputAsset
     * @param string $outputAsset
     * @param string $outputAssetOptions
     * @param string $outputAssetName
     * @return string
     */
    private function getMediaServicesTaskBody($inputAsset, $outputAsset, $outputAssetOptions, $outputAssetName) {
        return '<?xml version="1.0" encoding="utf-8"?><taskBody><inputAsset>' . $inputAsset . '</inputAsset><outputAsset assetCreationOptions="' . $outputAssetOptions . '" assetName="' . $outputAssetName . '">' . $outputAsset . '</outputAsset></taskBody>';
    }

    /**
     * ジョブ情報を更新する
     *
     * @param string $jobId
     * @param string $jobState
     * @return none
     */
    private function updateJob($jobId, $jobState)
    {
        $this->logger->log('start function: ' . __FUNCTION__);
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        // ジョブ情報をDBに登録
        $mediaId = self::$mediaId;
        $query = "UPDATE media"
               . " SET job_id = '{$jobId}', job_state = '{$jobState}', updated_at = datetime('now', 'localtime')"
               . " WHERE id = '{$mediaId}'";
        $this->logger->log('$query:' . $query);
        $this->db->exec($query);

        $this->logger->log('end function: ' . __FUNCTION__);
    }
}

?>