<th>
    <label>
        <input type="checkbox" value="" class="select-all-medias">
    </label>
</th>
<?php
$headers = [
    'mcode'            => '作品コード',
    'movie_name'       => '作品名',
    'category_id'      => 'カテゴリー',
    'version'          => 'バージョン',
    'start_at'         => '公開開始',
    'end_at'           => '公開終了',
    'size'             => 'サイズ(MB)',
    'playtime_seconds' => '再生時間',
    'user_id'          => 'ユーザー',
    'job_state'        => 'ジョブ進捗',
    'updated_at'       => '更新日時',
];

if ($app->config('debug')) {
    $headers['asset_id'] = 'アセット';
    $headers['job_id'] = 'ジョブ';
}
?>
<?php foreach ($headers as $key => $name) { ?>
<?php if ($searchConditions['orderby'] == $key && $searchConditions['sort'] == 'desc') { ?>
<th><a href="/medias?<?php echo http_build_query(array_merge($_GET, array('orderby' => $key, 'sort' => 'asc'))) ?>"><?php echo $name ?> <span class="glyphicon glyphicon-sort-by-alphabet-alt"></span></a></th>
<?php } else if ($searchConditions['orderby'] == $key && $searchConditions['sort'] == 'asc') { ?>
<th><a href="/medias?<?php echo http_build_query(array_merge($_GET, array('orderby' => $key, 'sort' => 'desc'))) ?>"><?php echo $name ?> <span class="glyphicon glyphicon-sort-by-alphabet"></span></a></th>
<?php } else { ?>
<th><a href="/medias?<?php echo http_build_query(array_merge($_GET, array('orderby' => $key, 'sort' => 'desc'))) ?>"><?php echo $name ?></a></th>
<?php } ?>
<?php } ?>
<th></th>