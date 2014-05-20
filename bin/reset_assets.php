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
    'logFile' => __DIR__ . '/../log/bin/reset_assets/reset_assets_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start reset_assets');

set_time_limit(0);

try {
    $query = file_get_contents(__DIR__ . '/../db/initialize.sql');
    $mediaServicesWrapper = $context->azureContext->getMediaServicesWrapper();

    $assets = $mediaServicesWrapper->getAssetList();
    $context->logger->log('assets:' .count($assets));

    $yesterday = new \DateTime('now -1 day');
    foreach ($assets as $asset) {
        if ($asset->getCreated()->getTimestamp() < $yesterday->getTimestamp()) {
            $mediaServicesWrapper->deleteAsset($asset);
        }
    }
} catch (Exception $e) {
    $context->logger->log('reset_assets throw exception. message:' . $e->getMessage());
}

$context->logger->log(date('[Y/m/d H:i:s]') . ' end reset_assets');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>