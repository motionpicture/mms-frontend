<?php
$url = $media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING];
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
    if (!ua.isiOS && !ua.isAndroid && !ua.isTablet) {
        Silverlight.createObject(
            '/smoothstreamingplayer-2.2010.1001.1/SmoothStreamingPlayer.xap',
            $('#{$playerId}').get(0),
            'silverlight_{$playerId}',
            {
                width: '280',
                height: '210',
                autoUpgrade: 'true',
                minRuntimeVersion: '4.0.50401.0',
                background: '#FFFFFF',
            },
            {
                onError: null,
                onLoad: null
            },
            'mediaurl={$url},autoplay=false',
            null
        );
    } else {
    	$('#{$playerId}').replaceWith('<p>Silverlight非対応ブラウザです</p>');
    }
});
</script>
<div id="{$playerId}"></div>
EOM;
?>
