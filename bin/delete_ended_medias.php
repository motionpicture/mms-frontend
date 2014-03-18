<?php
require_once('EndedMedias.php');
$endedMedias = new \Mms\Bin\EndedMedias();

$endedMedias->log(date('[Y/m/d H:i:s]') . ' start delete ended medias');

$endedMedias->delete();

$endedMedias->log(date('[Y/m/d H:i:s]') . ' end delete ended medias');

?>