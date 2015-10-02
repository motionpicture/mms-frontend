<?php
require_once dirname(__FILE__) . '/../apps/Api/Lib/Slim.php';

$app = new \Mms\Api\Lib\Slim([
    'log.enable' => true,
    'templates.path' => dirname(__FILE__) . '/../apps/Api/Templates'
]);

$app->hook('slim.before', function () use ($app) {
    $app->view->setData([
        'app' => $app
    ]);
});

$app->get('/media/stream/:mcode/:categoryId/:type/', '\Mms\Api\Controllers\MediaController:stream')->name('media_stream'); 
$app->get('/media/stream/:mcode/:categoryId/:type/:version', '\Mms\Api\Controllers\MediaController:streamByVersion')->name('media_stream_by_version');
$app->get('/streamable_medias', '\Mms\Api\Controllers\MediaController:streamables')->name('streamable_medias'); // ストリーム可能なメディア情報を全て取得する

$app->run();

?>