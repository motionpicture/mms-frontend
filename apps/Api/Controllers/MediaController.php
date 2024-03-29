<?php
namespace Mms\Api\Controllers;

class MediaController extends BaseController
{
    /**
     * メディアのストリーミングURL
     *
     * @param $mcode      作品コード
     * @param $categoryId カテゴリーID
     * @param $type       ストリームタイプ
     */
    public function stream($mcode, $categoryId, $type)
    {
        try {
            // 最新バージョンを確定
            $query = "SELECT MAX(version) AS max_version FROM media WHERE"
                   . " deleted_at = ''"
                   . " AND mcode = " . $this->pdo->quote($mcode)
                   . " AND category_id = " . $this->pdo->quote($categoryId);
            $statement = $this->pdo->query($query);
            $maxVersion = $statement->fetchColumn();

            // メディア存在チェック
            if (is_null($maxVersion)) {
                return $this->app->output('FAILURE', '指定の条件に対応するメディアは存在しません');
            }

            // バージョン指定でリダイレクト
            $redirect = $this->app->urlFor('media_stream_by_version', [
                'mcode'      => $mcode,
                'categoryId' => $categoryId,
                'type'       => $type,
                'version'    => $maxVersion
            ]);

            $this->app->redirect($redirect, 301);
        } catch (\Exception $e) {
            throw $e;
        }

        $e = new \Exception('予期せぬエラー');
        throw $e;
    }

    /**
     * メディアのストリーミングURL(バージョン指定)
     *
     * @param $mcode      作品コード
     * @param $categoryId カテゴリーID
     * @param $type       ストリームタイプ
     * @param $version    バージョン
     */
    public function streamByVersion($mcode, $categoryId, $type, $version)
    {
        try {
            $query = 'SELECT m1.*, c.name AS category_name';
            $query .= ' FROM media AS m1'
                    . ' INNER JOIN category AS c ON m1.category_id = c.id';
            $where = "m1.deleted_at = ''"
                   . " AND m1.mcode = " . $this->pdo->quote($mcode)
                   . " AND m1.category_id = " . $this->pdo->quote($categoryId)
                   . " AND m1.version = " . $this->pdo->quote($version)
                   . " LIMIT 1";
            $query .= ' WHERE ' . $where;
            $statement = $this->pdo->query($query);
            $media = $statement->fetch();

            // メディア存在チェック
            if (!isset($media['id'])) {
                return $this->app->output('FAILURE', '指定の条件に対応するメディアは存在しません');
            }

            // 公開開始日時チェック
            if ($media['start_at'] != '' && $media['start_at'] > date('Y-m-d H:i:s')) {
                return $this->app->output('FAILURE', '未公開動画です');
            }

            // 公開終了日時チェック
            if ($media['end_at'] != '' && $media['end_at'] < date('Y-m-d H:i:s')) {
                return $this->app->output('FAILURE', '公開期間を過ぎています');
            }

            // ジョブステータスチェック
            if (!\Mms\Lib\JobState::isFinished($media['job_state'])) {
                return $this->app->output('FAILURE', 'エンコードタスクが' . \Mms\Lib\JobState::toString($media['job_state']) . 'です');
            }

            // ストリーミングURLの取得
            $query = "SELECT url FROM task WHERE media_id = '{$media['id']}' AND name = " . $this->pdo->quote($type);
            $statement = $this->pdo->query($query);
            $url = $statement->fetchColumn();

            // ストリーミングタイプチェック
            if (!$url) {
                return $this->app->output('FAILURE', '指定のストリームタイプに対応するURLは存在しません');
            }

            // 成功
            $media['url'] = $url;
            $options = [
                'media' => $media
            ];
            return $this->app->output('SUCCESS', '', $options);
        } catch (\Exception $e) {
            throw $e;
        }

        $e = new \Exception('予期せぬエラー');
        throw $e;
    }

    public function streamables()
    {
        // 公開中かつジョブステータス完了のメディアを取得
        $medias = [];

        // 検索条件
        // デフォルトは、公開期間中のメディアのみ
        $conditions = [
            'showing' => true
        ];
        if (isset($_GET['mcodes']) && !empty($_GET['mcodes'])) {
           $conditions['mcodes'] = explode(',', $_GET['mcodes']);
        }
        if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
           $conditions['category_id'] = $_GET['category_id'];
        }
//         if (isset($_GET['showing']) && $_GET['showing'] == '1') {
//             $conditions['showing'] = true;
//         }
//         if (isset($_GET['showing']) && $_GET['showing'] == '0') {
//            $conditions['showing'] = false;
//         }

        try {
            $query = 'SELECT m1.id, m1.code, m1.mcode, m1.category_id, m1.version, m1.size, m1.extension, m1.movie_name, m1.playtime_string, m1.playtime_seconds, m1.start_at, m1.end_at, category.name AS category_name';
            $query .= ' FROM media AS m1'
                    . ' INNER JOIN category ON m1.category_id = category.id';

            // 未削除、かつ、ジョブ進捗完了のメディアを取得
            $where = "m1.deleted_at = ''"
                   . " AND m1.job_state = " . \WindowsAzure\MediaServices\Models\Job::STATE_FINISHED;

            // 最新バージョンのメディアのみ取得
            $where .= " AND m1.version = (SELECT MAX(m2.version) FROM media AS m2 WHERE m1.code =  m2.code AND m2.deleted_at = '')";

            // 検索条件を追加
            if (isset($conditions['showing']) && $conditions['showing']) {
                $where .= " AND ("
                       . "m1.start_at IS NULL OR m1.start_at = ''"
                       . " OR (m1.start_at IS NOT NULL AND m1.start_at <> '' AND m1.start_at <= datetime('now', 'localtime'))"
                       . ")"
                       . " AND ("
                       . "m1.end_at IS NULL OR m1.end_at = ''"
                       . " OR (m1.end_at IS NOT NULL AND m1.end_at <> '' AND m1.end_at >= datetime('now', 'localtime'))"
                       . ")";
            }

            if (isset($conditions['showing']) && !$conditions['showing']) {
              $where .= " AND ("
                      . "(m1.start_at IS NOT NULL AND m1.start_at <> '' AND m1.start_at > datetime('now', 'localtime'))"
                      . " OR "
                      . "(m1.end_at IS NOT NULL AND m1.end_at <> '' AND m1.end_at < datetime('now', 'localtime'))"
                      . ")";
            }

            $quote = function($string) {
                return $this->pdo->quote($string);
            };
            if (isset($conditions['mcodes']) && is_array($conditions['mcodes'])) {
                $in = implode(',', array_map($quote, $conditions['mcodes']));
                $where .= " AND m1.mcode IN ({$in})";
            }

            if (isset($conditions['category_id'])) {
                $categoryId = $this->pdo->quote($conditions['category_id']);
                $where .= " AND m1.category_id = {$categoryId}";
            }

            $query .= ' WHERE ' . $where;
            $statement = $this->pdo->query($query);
            while ($res = $statement->fetch()) {
                // タスクがあればリストに追加する
                $query2 = "SELECT name, url FROM task WHERE media_id = '{$res['id']}'";
                $statement2 = $this->pdo->query($query2);
                $tasks = $statement2->fetchAll();

                if (!empty($tasks)) {
                    $urls = [];
                    foreach ($tasks as $task) {
                        $urls[$task['name']] = $task['url'];
                    }

                    $res['urls'] = $urls;
                    $medias[] = $res;
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->app->output('SUCCESS', '', [
            'medias' => $medias
        ]);
    }
}
