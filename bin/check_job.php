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
    'logFile' => __DIR__ . '/../log/bin/check_job/check_job_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/Contexts/InEncodeMedias.php';
$inEncodeMedias = new \Mms\Bin\Contexts\InEncodeMedias(
    $userSettings
);

$inEncodeMedias->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$inEncodeMedias->logger->log(date('[Y/m/d H:i:s]') . ' start check_job');

$inEncodeMedias->checkJobState();

$inEncodeMedias->logger->log(date('[Y/m/d H:i:s]') . ' end check_job');
$inEncodeMedias->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>