<?php
/**
 * 削除されたメディアに関して、ジョブやタスクをリセットする
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
    'logFile' => __DIR__ . '/../log/bin/reset_deleted_medias/reset_deleted_medias_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start reset_deleted_medias');

$medias2reset = [];
try {
    // 削除済み、かつ、ジョブ未リセット状態のメディアを取得する
    $query = "SELECT id, asset_id, job_id FROM media WHERE deleted_at <> '' AND job_id <> ''";
    $result = $context->db->query($query);
    $medias2reset = $result->fetchAll();
} catch (\Exception $e) {
    $context->logger->log('selecting medias throw exception. message:' . $e->getMessage());
}

$context->logger->log('$medias2reset:' . print_r($medias2reset, true));

require_once __DIR__ . '/Contexts/PostEncodeMedia.php';
foreach ($medias2reset as $media) {
    $postEncodeMediaContext = new \Mms\Bin\Contexts\PostEncodeMedia(
        $userSettings,
        $media['id'],
        $media['asset_id'],
        $media['job_id']
    );

    $postEncodeMediaContext->post2pre();
}

$context->logger->log(date('[Y/m/d H:i:s]') . ' end reset_deleted_medias');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>