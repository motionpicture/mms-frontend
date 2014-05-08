<?php
session_cache_limiter(false);
session_start();

require_once dirname(__FILE__) . '/../../slim_apps/Api/Lib/Slim.php';

$app = new \Mms\Api\Lib\Slim([
    'log.enable' => true,
//     'log.path'    => '/../log',
//     'log.level'    => 8,
    'log.writer' => new \Slim\LogWriter(fopen(dirname(__FILE__) . '/../../log/api.log', 'a+')),
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
                $options = ['url' => $url];
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
    } catch (Exception $e) {
        $app->log->error('message:' . $e->getMessage());
        throw $e;
    }

    $e = new Exception('予期せぬエラー');
    $app->log->error('message:' . $e->getMessage());
    throw $e;
})->name('media_stream');

/**
 * Error Handler(非デバッグモードの場合のみ動作する)
 */
$app->error(function (\Exception $e) use ($app) {
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