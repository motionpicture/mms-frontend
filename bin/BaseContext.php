<?php
namespace Mms\Bin;

ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    require_once __DIR__ . '/../lib/' . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
});

/**
 * バッチ処理のベース文脈クラス
 *
 * @package   Mms\Bin
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class BaseContext
{
    public $db;
    public $logger;
    public $azureContext;
    public $userSettings;
    private static $isDev = false;
    private static $mode;

    function __construct($userSettings = [])
    {
        $this->userSettings = $userSettings;
        self::$mode = $userSettings['mode'];

        if (self::$mode == 'development') {
            self::$isDev = true;
        }

        $isDisplayOutput = false;
        if (php_sapi_name() == 'cli' && self::$isDev) {
            $isDisplayOutput = true;
        }
        $this->logger = \Mms\Lib\Logger::getInstance();
        $this->logger->initialize(
            $userSettings['logFile'],
            self::$isDev,
            $isDisplayOutput
        );

        $this->azureContext = new \Mms\Lib\AzureContext(self::$mode);

        $this->db = \Mms\Lib\PDO::getInstance(self::$mode);
    }

    /**
     * 環境文字列を取得する
     *
     * @return string
     */
    public function getMode()
    {
        return self::$mode;
    }

    /**
     * 開発環境かどうかを取得する
     *
     * @return boolean
     */
    public function getIsDev()
    {
        return self::$isDev;
    }

    /**
     * エラー通知
     *
     * @param string $message
     */
    function reportError($message)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $errorsIniArray = parse_ini_file(__DIR__ . '/../config/errors.ini', true);
        $errorsConfig = $errorsIniArray[$this->getMode()];

        $to = implode(',', $errorsConfig['to']);
        $subject = $errorsConfig['subject'];
        $headers = 'From: webmaster@pmmedia.cloudapp.net' . "\r\n"
                 . 'Reply-To: webmaster@pmmedia.cloudapp.net';
        if (!mail($to, $subject, $message, $headers)) {
            $this->log('reportError fail. $message:' . print_r($message, true));
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * メディアのジョブ情報&タスクをリセットする
     *
     * @param array $mediaIds
     * @return boolean
     */
    protected function resetMedias($mediaIds)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $count4updateTask = 0;
        $count4deleteTask = 0;
        $isReset = false;

        if (!empty($mediaIds)) {
            $this->db->beginTransaction();
            try {
                // メディアのジョブをリセット
                $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), job_id = '', job_state = '', job_start_at = '', job_end_at = '' WHERE id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log('$query:' . $query);
                $count4updateTask = $this->db->exec($query);

                // タスク削除
                $query = "DELETE FROM task WHERE media_id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log('$query:' . $query);
                $count4deleteTask = $this->db->exec($query);

                $this->db->commit();
                $isReset = true;
            } catch (\Exception $e) {
                $this->db->rollBack();
                $this->logger->log('resetMedias throw exception. message:' . $e->getMessage());
            }
        }

        $this->logger->log('$count4updateTask: ' . $count4updateTask);
        $this->logger->log('$count4deleteTask: ' . $count4deleteTask);

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $isReset;
    }

    /**
     * ジョブのアウトプットアセットを削除する
     *
     * @param string $jobId
     * @return boolean
     */
    protected function deleteOutputAssets($jobId)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $isDeleted = false;

        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

            $outputAssets = $mediaServicesWrapper->getJobOutputMediaAssets($jobId);
            $this->logger->log('$outputAssets:' . count($outputAssets));
            foreach ($outputAssets as $outputAsset) {
                $mediaServicesWrapper->deleteAsset($outputAsset);
            }

            $isDeleted = true;
        } catch (\Exception $e) {
            $this->logger->log('deleteOutputAssets throw exception. jobId:' . $jobId . ' message:' . $e->getMessage());
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $isDeleted;
    }
}
?>
