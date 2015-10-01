<?php
/**
 * inputアセットを生成しなおす
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
    'logFile' => __DIR__ . '/../log/bin/recreate_input_assets/recreate_input_assets_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start recreate_input_assets');

$medias2reset = [];
try {
    // エンコード前の状態に戻したいメディアを取得する
    $query = "SELECT id, asset_id, job_id FROM media WHERE asset_id <> '' AND deleted_at = ''";
    $result = $context->db->query($query);
    $medias2recreate = $result->fetchAll();
} catch (\Exception $e) {
    $context->logger->log('selecting medias throw exception. message:' . $e->getMessage());
}

$context->logger->log('medias2recreate:' . count($medias2recreate));

require_once __DIR__ . '/Contexts/PostEncodeMedia.php';
foreach ($medias2recreate as $media) {
    try {
        $postEncodeMediaContext = new \Mms\Bin\Contexts\PostEncodeMedia(
            $userSettings,
            $media['id'],
            $media['asset_id'],
            $media['job_id']
        );

        $postEncodeMediaContext->recreateInputAsset();
    } catch (\Exception $e) {
        $context->logger->log("fail in recreateInputAsset: mediaId:{$media['id']} " . $e->getMessage());
    }
}

$context->logger->log(date('[Y/m/d H:i:s]') . ' end recreate_input_assets');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>