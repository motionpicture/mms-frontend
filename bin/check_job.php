<?php
/**
 * ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアに関して
 * ジョブ進捗を確信し、完了していればURLを発行する
 */

// 環境取得
$modeFile = __DIR__ . '/../mode.php';
if (false === is_file($modeFile)) {
    exit('The application "mode file" does not exist.');
}
require($modeFile);
if (empty($mode)) {
    exit('The application "mode" does not exist.');
}

$userSettings = [
    'mode'    => $mode,
    'logFile' => __DIR__ . '/../log/bin/check_job/check_job_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start check_job');

$medias = [];
try {
    // ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアを取得する
    $query = sprintf('SELECT * FROM media WHERE job_state = \'%s\' OR job_state = \'%s\' OR job_state = \'%s\'',
                    WindowsAzure\MediaServices\Models\Job::STATE_QUEUED,
                    WindowsAzure\MediaServices\Models\Job::STATE_SCHEDULED,
                    WindowsAzure\MediaServices\Models\Job::STATE_PROCESSING);
    $result = $context->db->query($query);
    $medias = $result->fetchAll();
} catch (\Exception $e) {
    $context->logger->log('selecting medias throw exception. message:' . $e->getMessage());
}

require_once __DIR__ . '/Contexts/InEncodeMedia.php';
foreach ($medias as $media) {
    $context->logger->log("\n--------------------\n" . $media['id'] . ' checking job state...' . "\n--------------------\n");

    // ひとつのメディアでの失敗が全体に影響しないように、ひとつずつtry-catch
    try {
        $inEncodeMedia = new \Mms\Bin\Contexts\InEncodeMedia(
            $userSettings,
            $media['id'],
            $media['job_id'],
            $media['job_state']
        );

        $url = $inEncodeMedia->tryDeliverMedia();

        // URLが発行されればメール送信
        if (!is_null($url)) {
            $context->sendEmail($media['code'], $media['user_id']);
        }
    } catch (\Exception $e) {
        $message = 'tryDeliverMedia throw exception. $mediaId:' . $media['id'] . ' message:' . $e->getMessage();
        $context->logger->log($message);
        $context->reportError($message);
    }
}

$context->logger->log(date('[Y/m/d H:i:s]') . ' end check_job');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>