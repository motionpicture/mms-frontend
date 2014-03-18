<?php
$filepath = fgets(STDIN);
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

require_once('UploadedFile.php');
$uploadedFile = new \Mms\Bin\UploadedFile($filepath);

$uploadedFile->log(date('[Y/m/d H:i:s]') . ' start process');

$uploadedFile->process();

$uploadedFile->log(date('[Y/m/d H:i:s]') . ' end process');

?>