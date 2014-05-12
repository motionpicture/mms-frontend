<?php
session_cache_limiter(false);
session_start();

require_once dirname(__FILE__) . '/../../slim_apps/Api/Lib/Slim.php';

$app = new \Mms\Api\Lib\Slim([
    'log.enable' => true,
//     'log.path'    => '/../log',
//     'log.level'    => 8,
    'templates.path' => dirname(__FILE__) . '/../../slim_apps/Api/Templates'
]);

$app->hook('slim.before', function () use ($app) {
    $app->view->setData([
        'app' => $app
    ]);
});

/**
 * メディアのストリーミングURL
 */
$app->get('/media/stream/:mcode/:categoryId/:type', function ($mcode, $categoryId, $type) use ($app) {
    $app->log->debug('args: ' . print_r(func_get_args(), true));

    try {
        // 最新のメディアを取得
        $query = "SELECT id, job_state FROM media WHERE mcode = '{$mcode}' AND category_id = '{$categoryId}' ORDER BY media.version DESC LIMIT 1";
        $statement = $app->db->query($query);
        $media = $statement->fetch();

        if (isset($media['id'])) {
          if (\Mms\Lib\JobState::isFinished($media['job_state'])) {
            // ストリーミングURLの取得
            $query = "SELECT url FROM task WHERE media_id = '{$media['id']}' AND name = '{$type}'";
            $statement = $app->db->query($query);
            $url = $statement->fetchColumn();

            if ($url != '') {
                $options = [
                    'url' => $url
                ];
                return $app->output('SUCCESS', '', $options);
            } else {
                return $app->output('FAILURE', '指定のストリームタイプに対応するURLは存在しません');
            }
          } else {
              return $app->output('FAILURE', \Mms\Lib\JobState::toString($media['job_state']));
          }
        } else {
            return $app->output('FAILURE', '指定の作品コードとカテゴリーに対応するメディアは存在しません');
        }
    } catch (\Exception $e) {
        throw $e;
    }

    $e = new \Exception('予期せぬエラー');
    throw $e;
})->name('media_stream');

/**
 * ストリーム可能なメディア情報を全て取得する
 */
$app->get('/streamable_medias', function () use ($app) {
    $app->log->debug('args: ' . print_r(func_get_args(), true));

    // 公開中かつジョブステータス完了のメディアを取得
    $medias = [];
    try {
        $query = 'SELECT m1.id, m1.code, m1.mcode, m1.category_id, m1.version, m1.size, m1.extension, m1.movie_name, m1.playtime_string, m1.playtime_seconds, m1.start_at, m1.end_at, category.name AS category_name';
        $query .= ' FROM media AS m1'
                . ' INNER JOIN category ON m1.category_id = category.id';

        $where = "m1.id IS NOT NULL"
                . " AND m1.job_state == " . \WindowsAzure\MediaServices\Models\Job::STATE_FINISHED
                . " AND m1.start_at IS NOT NULL AND m1.end_at IS NOT NULL"
                . " AND m1.start_at <> '' AND m1.end_at <> ''"
                . " AND m1.start_at <= datetime('now', 'localtime') AND m1.end_at >= datetime('now', 'localtime')";
        // 最新バージョンのメディアのみ取得
        $where .= " AND m1.version = (SELECT MAX(m2.version) FROM media AS m2 WHERE m1.code =  m2.code)";

        $query .= ' WHERE ' . $where;
        $statement = $app->db->query($query);
        while ($res = $statement->fetch()) {
          // タスクがあればリストに追加する
            $query2 = "SELECT name, url FROM task WHERE media_id = '{$res['id']}'";
            $statement2 = $app->db->query($query2);
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

    return $app->output('SUCCESS', '', [
        'medias' => $medias
    ]);
})->name('streamable_medias');

/**
 * Error Handler(非デバッグモードの場合のみ動作する)
 */
$app->error(function (\Exception $e) use ($app) {
    $app->log->error('route:{router}', [
        'exception' => $e,
        'router' => print_r($app->router->getCurrentRoute()->getName(), true)
    ]);

    return $app->output('FAILURE', $e->getMessage());
});

/**
 * Not Found Handler
 */
$app->notFound(function () use ($app) {
    return $app->output('FAILURE', '404 Page Not Found');
});

$app->run();

?>