<?php
/**
 * 公開終了日時の過ぎたメディアに関して
 * 削除状態に変更する
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
    'logFile' => __DIR__ . '/../log/bin/delete_ended_medias/delete_ended_medias_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start delete_ended_medias');

// 未削除、かつ、公開終了日時の過ぎたメディアを取得
$mediaIds = [];
try {
    $where = "deleted_at = ''"
           . " AND start_at IS NOT NULL AND end_at IS NOT NULL"
           . " AND start_at <> '' AND end_at <> ''"
           . " AND start_at < datetime('now', 'localtime') AND end_at < datetime('now', 'localtime')";
    $query = "SELECT id FROM media WHERE " . $where;
    $statement = $context->db->query($query);
    while ($res = $statement->fetch()) {
        $mediaIds[] = $res['id'];
    }
} catch (\Exception $e) {
    $context->logger->log('selecting medias throw exception. message:' . $e->getMessage());
    return;
}
$context->logger->log('$mediaIds:' . print_r($mediaIds, true));

$count4updateMedia = 0;
if (!empty($mediaIds)) {
    try {
        // メディア削除
        $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), deleted_at = datetime('now', 'localtime') WHERE id IN ('" . implode("','", $mediaIds) . "')";
        $context->logger->log('$query:' . $query);
        $count4updateMedia = $context->db->exec($query);
    } catch (\Exception $e) {
        $context->logger->log('deleteWithTasks throw exception. message:' . $e->getMessage());
    }
}
$context->logger->log('$count4updateMedia:' . $count4updateMedia);

$context->logger->log(date('[Y/m/d H:i:s]') . ' end delete_ended_medias');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>