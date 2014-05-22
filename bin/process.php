<?php
/**
 * ブラウザあるいはFTPにてアップロードされたファイルをメディアサービスへアップロードする
 */

$filepath = fgets(STDIN);
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

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
    'logFile' => __DIR__ . '/../log/bin/process/process_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/Contexts/UploadedFile.php';
$uploadedFile = new \Mms\Bin\Contexts\UploadedFile(
    $userSettings,
    $filepath
);

$uploadedFile->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$uploadedFile->logger->log(date('[Y/m/d H:i:s]') . ' start process');

list($mediaId, $assetId) = $uploadedFile->path2asset();

$uploadedFile->logger->log(date('[Y/m/d H:i:s]') . ' end process');
$uploadedFile->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>