<?php
require_once('JobState.php');
$jobState = new \Mms\Bin\JobState();

$jobState->log(date('[Y/m/d H:i:s]') . ' start check_job');

$jobState->update();

$jobState->log(date('[Y/m/d H:i:s]') . ' end check_job');

?>