<?php
$url = str_replace('http://', 'https://', $media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH]);
$code = <<< EOM
<script>
$(function(){
    // Media Source Extension対応ブラウザのみ
    // https://dvcs.w3.org/hg/html-media/raw-file/default/media-source/media-source.html
    var url = '{$url}';
    if (typeof MediaSource == 'function') {
        var context = new Dash.di.DashContext();
        var player = new MediaPlayer(context);
        player.startup();
        player.attachView($('#{$playerId}').get(0));
        player.setAutoPlay(false);
        player.attachSource(url);
    } else {
        $('#{$playerId}').replaceWith('<p>mpeg dash非対応ブラウザです</p>');
    }
});
</script>
<video width="280" height="210" id="{$playerId}" controls>MPEG DASH</video>
EOM;
?>
