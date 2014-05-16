<?php

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
    'logFile' => dirname(__FILE__) . '/../log/bin/init_db/init_db_' . $mode . '_' . date('Ymd') . '.log'
];

require_once('BaseContext.php');
$context = new \Mms\Bin\BaseContext($userSettings);

try {
    $query = file_get_contents(dirname(__FILE__) . '/../db/initialize.sql');
    $context->db->exec($query);
} catch (Exception $e) {
    $context->logger->log($e->getMessage());
}

?>