<?php
namespace Mms\Bin\Contexts;

require_once __DIR__ . '/../BaseContext.php';

set_time_limit(0);

/**
 * 公開期間の終了したメディアという文脈
 *
 * @package   Mms\Bin\Contexts
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class EndedMedias extends \Mms\Bin\BaseContext
{
    /**
     * __construct
     */
    function __construct($userSettings)
    {
        parent::__construct($userSettings);
    }

    public function delete()
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        // 未削除、かつ、公開終了日時の過ぎたメディアを取得
        $mediaIds = [];
        try {
            $where = "deleted_at == ''"
                   . " AND start_at IS NOT NULL AND end_at IS NOT NULL"
                   . " AND start_at <> '' AND end_at <> ''"
                   . " AND start_at < datetime('now', 'localtime') AND end_at < datetime('now', 'localtime')";
            $query = "SELECT id FROM media WHERE " . $where;
            $statement = $this->db->query($query);
            while ($res = $statement->fetch()) {
                $mediaIds[] = $res['id'];
            }
        } catch (\Exception $e) {
            $this->logger->log('selecting medias throw exception. message:' . $e->getMessage());
            return;
        }

        $this->logger->log('$mediaIds:' . print_r($mediaIds, true));

        $count4updateMedia = 0;

        if (!empty($mediaIds)) {
            try {
                // メディア削除
                $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), deleted_at = datetime('now', 'localtime') WHERE id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log('$query:' . $query);
                $count4updateMedia = $this->db->exec($query);
            } catch (\Exception $e) {
                $this->logger->log('deleteWithTasks throw exception. message:' . $e->getMessage());
            }
        }

        $this->logger->log('$count4updateMedia:' . $count4updateMedia);

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>