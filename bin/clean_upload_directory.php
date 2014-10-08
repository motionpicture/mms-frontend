<?php
/**
 * アップロードディレクトリに残ったゴミファイルを物理削除する
 */


// 環境取得
$modeFile = __DIR__ . '/../mode.php';
if (false === is_file($modeFile)) {
    exit('The application "mode file" does not exist.');
}
require($modeFile);
if (empty($mode)) {
    exit('The application "mode" does not exist.');
}

$userSettings = [
    'mode'    => $mode,
    'logFile' => __DIR__ . '/../log/bin/clean_upload_directory/clean_upload_directory_' . $mode . '_' . date('Ymd') . '.log'
];

require_once __DIR__ . '/BaseContext.php';
$context = new \Mms\Bin\BaseContext($userSettings);

$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");
$context->logger->log(date('[Y/m/d H:i:s]') . ' start clean_upload_directory');

$dir = __DIR__ . '/../uploads';
$iterator = new RecursiveDirectoryIterator($dir);
$iterator = new RecursiveIteratorIterator($iterator);

$files4delete = [];
foreach ($iterator as $fileinfo) {
    try {
        // $fileinfoはSplFiIeInfoオブジェクト
        // ドット始まりのファイルは除外
        if ($fileinfo->isFile() && substr($fileinfo->getFilename(), 0, 1) != '.') {
            // 3日以上経過していれば追加
            $absence = time() - $fileinfo->getCTime();
            if ($absence > 60 * 60 * 24 * 3) {
                $files4delete[] = [
                    'path'  => $fileinfo->getPathname(),
                    'ctime' => date('Y-m-d H:i:s', $fileinfo->getCTime()) // inode変更時刻
                ];
            }
        }
    } catch (Exception $e) {
        $context->logger->log('$fileinfo-> throw exception. message:' . $e->getMessage());
    }
}

$context->logger->log('$files4delete:' . print_r($files4delete, true));

// 削除
foreach ($files4delete as $file) {
    unlink($file['path']);
    $context->logger->log('A file has been deleted. path:' . $file['path']);
}

$context->logger->log(date('[Y/m/d H:i:s]') . ' end clean_upload_directory');
$context->logger->log("\n////////////////////////////////////////////////////////////\n////////////////////////////////////////////////////////////\n");

?>