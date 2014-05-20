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
    'logFile' => __DIR__ . '/../log/bin/reset_deleted_medias/reset_deleted_medias_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/Contexts/DeletedMedias.php';
$endedMedias = new \Mms\Bin\Contexts\DeletedMedias(
    $userSettings
);

$endedMedias->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$endedMedias->logger->log(date('[Y/m/d H:i:s]') . ' start reset deleted medias');

$endedMedias->reset();

$endedMedias->logger->log(date('[Y/m/d H:i:s]') . ' end reset deleted medias');
$endedMedias->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>