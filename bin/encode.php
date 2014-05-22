<?php
/**
 * ジョブ未登録のメディアに対して、ジョブを作成する
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
    'logFile' => __DIR__ . '/../log/bin/encode/encode_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start encode');

$preEncodeMedias = [];
try {
    // アセット作成済み、ジョブ未登録、未削除のメディアを取得
    $query = "SELECT id, asset_id FROM media WHERE"
           . " asset_id <> '' AND job_id == '' AND deleted_at == ''";
    $result = $context->db->query($query);
    $preEncodeMedias = $result->fetchAll();
} catch (\Exception $e) {
    $context->logger->log('selecting medias throw exception. message:' . $e->getMessage());
}

$context->logger->log('$preEncodeMedias:' . count($preEncodeMedias));

// ひとつずつエンコード
require_once __DIR__ . '/Contexts/PreEncodeMedia.php';
foreach ($preEncodeMedias as $preEncodeMedia) {
    $preEncodeMediaContext = new \Mms\Bin\Contexts\PreEncodeMedia(
        $userSettings,
        $preEncodeMedia['id'],
        $preEncodeMedia['asset_id']
    );

    $preEncodeMediaContext->encode();
}

$context->logger->log(date('[Y/m/d H:i:s]') . ' end encode');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>