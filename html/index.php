<?php
session_cache_limiter(false);
session_start();

require_once __DIR__ . '/../apps/Frontend/Lib/Slim.php';

$app = new \Mms\Frontend\Lib\Slim([
    'log.enable' => true,
//     'log.path'    => '/../log',
//     'log.level'    => 8,
    'templates.path' => dirname(__FILE__) . '/../apps/Frontend/Templates',
]);

$app->hook('slim.before', function () use ($app) {
    $app->view->setData([
        'app' => $app
    ]);
});

/**
 * HOME
 */
$app->get('/', function () use ($app) {
    $app->redirect('/medias', 303);
})->name('homepage');

$app->map('/media/new', '\Mms\Frontend\Controllers\MediaController:create')->via('GET', 'POST')->name('media_new'); // メディア登録
$app->get('/media/new/progress/:name', '\Mms\Frontend\Controllers\MediaController:createProgress')->name('media_create_progress'); // メディア登録進捗
$app->get('/medias', '\Mms\Frontend\Controllers\MediaController:index')->name('medias'); // メディア一覧
$app->get('/media/:code', '\Mms\Frontend\Controllers\MediaController:show')->name('media_by_code'); // メディア詳細
$app->get('/media/:id/download', '\Mms\Frontend\Controllers\MediaController:download')->name('media_download'); // メディアダウンロード
$app->post('/media/:code/update_by_code', '\Mms\Frontend\Controllers\MediaController:updateByCode')->name('media_update_by_code'); // コードからメディア更新
$app->post('/media/:id/delete', '\Mms\Frontend\Controllers\MediaController:delete')->name('media_delete'); // メディア削除
$app->get('/media/:id/restore', '\Mms\Frontend\Controllers\MediaController:restore')->name('media_restore'); // 削除されたメディアの復活
$app->post('/media/:id/reencode', '\Mms\Frontend\Controllers\MediaController:reencode')->name('media_reencode'); // メディア再エンコード

$app->post('/medias/update_by_code', '\Mms\Frontend\Controllers\MediasController:updateByCode')->name('medias_update_by_code'); // コードからメディアをまとめて更新
$app->get('/medias/download', '\Mms\Frontend\Controllers\MediasController:download')->name('medias_download'); // メディアをまとめてダウンロード

$app->map('/user/edit', '\Mms\Frontend\Controllers\UserController:edit')->via('GET', 'POST')->name('user_edit'); // アカウント編集

$app->run();

?>