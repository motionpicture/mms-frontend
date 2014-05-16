<?php
$filepath = fgets(STDIN);
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

// 環境取得
$modeFile = dirname(__FILE__) . '/../mode.php';
if (false === is_file($modeFile)) {
    exit('The application "mode file" does not exist.');
}
require($modeFile);
if (empty($mode)) {
    exit('The application "mode" does not exist.');
}

$userSettings = [
    'mode'    => $mode,
    'logFile' => dirname(__FILE__) . '/../log/bin/process/process_' . $mode . '_' . date('Ymd') . '.log'
];

require_once('UploadedFile.php');
$uploadedFile = new \Mms\Bin\UploadedFile(
    $userSettings,
    $filepath
);

$uploadedFile->logger->log(date('[Y/m/d H:i:s]') . ' start process');

list($mediaId, $assetId) = $uploadedFile->path2asset();

if (!is_null($mediaId) && !is_null($assetId)) {
    require_once('PreEncodeMedia.php');
    $preEncodeMedia = new \Mms\Bin\PreEncodeMedia(
        $userSettings,
        $mediaId,
        $assetId
    );

    $preEncodeMedia->encode();
}

$uploadedFile->logger->log(date('[Y/m/d H:i:s]') . ' end process');

?>