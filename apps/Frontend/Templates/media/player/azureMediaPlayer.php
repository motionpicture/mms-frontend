<?php
$url = str_replace('http:', '', $media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING]);
$code = <<< EOM
<video id="{$playerId}" class="azuremediaplayer amp-default-skin"></video>
<script>
var styleNode = document.createElement("link");
styleNode.href = "//amp.azure.net/libs/amp/1.3.0/skins/amp-default/azuremediaplayer.min.css";
styleNode.rel = "stylesheet";
document.getElementsByTagName('head')[0].appendChild(styleNode);
var options = {
    autoplay: false,
    controls: true,
    width: "280",
    height: "210",
    poster: "",
    "nativeControlsForTouch": false,
    "logo": {enabled: false},
    "skinConfig": {audioTracksMenu: {enabled: false}}
};
var player = amp("{$playerId}", options);
player.src([{
    src: "{$url}",
    type: "application/vnd.ms-sstr+xml"
}]);
</script>
EOM;
?>
