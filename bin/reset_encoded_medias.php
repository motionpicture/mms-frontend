<?php
/**
 * エンコード済みのメディアを、エンコード前の状態に戻す
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
    'logFile' => __DIR__ . '/../log/bin/reset_encoded_medias/reset_encoded_medias_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start reset_encoded_medias');

$medias2reset = [];
try {
    // エンコード前の状態に戻したいメディアを取得する
    $query = "SELECT id, asset_id, job_id FROM media WHERE"
           . " asset_id <> '' AND job_id <> '' AND job_state == '' AND deleted_at == ''";
    $result = $context->db->query($query);
    $medias2reset = $result->fetchAll();
} catch (\Exception $e) {
    $context->logger->log('selecting medias throw exception. message:' . $e->getMessage());
}

$context->logger->log('$medias2reset:' . count($medias2reset));

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

$context->logger->log(date('[Y/m/d H:i:s]') . ' end reset_encoded_medias');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>