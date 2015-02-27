<?php
$url = $media['urls'][\Mms\Lib\Models\Task::NAME_HLS];
$code = <<< EOM
<script>
// ユーザーエージェントの判別
var ua = {};
ua.name = window.navigator.userAgent.toLowerCase();
ua.isiPhone = ua.name.indexOf('iphone') >= 0;
ua.isiPod = ua.name.indexOf('ipod') >= 0;
ua.isiPad = ua.name.indexOf('ipad') >= 0;
ua.isiOS = (ua.isiPhone || ua.isiPod || ua.isiPad);
ua.isAndroid = ua.name.indexOf('android') >= 0;
ua.isTablet = (ua.isiPad || (ua.isAndroid && ua.name.indexOf('mobile') < 0));

$(function(){
    var url = '{$url}';
    if (ua.isiOS || ua.isAndroid || ua.isTablet) {
        $('#{$playerId}').attr('src', url);
    } else {
        $('#{$playerId}').replaceWith('<p>Http Live Streaming非対応ブラウザです</p>');
    }
});
</script>
<video width="280" height="210" id = "{$playerId}" controls>HLS</video>
EOM;
?>
