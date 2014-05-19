<?php

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
    'logFile' => __DIR__ . '/../log/bin/delete_ended_medias/delete_ended_medias_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/Contexts/EndedMedias.php';
$endedMedias = new \Mms\Bin\Contexts\EndedMedias(
    $userSettings
);

$endedMedias->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$endedMedias->logger->log(date('[Y/m/d H:i:s]') . ' start delete ended medias');

$endedMedias->delete();

$endedMedias->logger->log(date('[Y/m/d H:i:s]') . ' end delete ended medias');
$endedMedias->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>