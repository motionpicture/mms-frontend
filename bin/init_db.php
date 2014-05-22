<?php
/**
 * DBを初期化する
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
    'logFile' => __DIR__ . '/../log/bin/init_db/init_db_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start init_db');

try {
    $query = file_get_contents(__DIR__ . '/../db/initialize.sql');
    $context->logger->log('$query:' . $query);
    $context->db->exec($query);
} catch (\Exception $e) {
    $context->logger->log('init_db throw exception. message:' . $e->getMessage());
}

$context->logger->log(date('[Y/m/d H:i:s]') . ' end init_db');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>