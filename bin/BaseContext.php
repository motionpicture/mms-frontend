<?php
namespace Mms\Bin;

use PEAR;
use Mail;
use Mail_mime;

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
    private static $host;

    function __construct($userSettings = [])
    {
        $this->userSettings = $userSettings;
        self::$mode = $userSettings['mode'];

        if (self::$mode == 'development') {
            self::$isDev = true;
            self::$host = 'localhost';
        } else {
            self::$host = 'media.comovieticket.jp';
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

        $this->azureContext = \Mms\Lib\AzureContext::getInstance(self::$mode);
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
     * @return none
     */
    function reportError($message)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $errorsIniArray = parse_ini_file(__DIR__ . '/../config/errors.ini', true);
        $errorsConfig = $errorsIniArray[$this->getMode()];

        $host = self::$host;
        $to = implode(',', $errorsConfig['to']);
        $subject = $errorsConfig['subject'];
        $headers = "From: webmaster@{$host}" . "\r\n"
                 . "Reply-To: webmaster@{$host}";
        if (!mail($to, $subject, $message, $headers)) {
            $this->logger->log('reportError fail. $message:' . print_r($message, true));
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * ストリームURL発行お知らせメールを送信する
     *
     * @param array $media
     * @return none
     */
    public function sendEmail($media)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $query = "SELECT email FROM user WHERE id = '{$media['user_id']}'";
        $statement = $this->db->query($query);
        $email = $statement->fetchColumn();
        $this->logger->debug('$email:' . $email);

        // 送信
        if ($email) {
            // 言語設定、内部エンコーディング指定
            mb_language('japanese');
            mb_internal_encoding('UTF-8');

            $host = self::$host;
            $to = $email;
            $subject = '[ムビチケ動画管理システム]ストリーミングURLが発行されました';
            $from = "webmaster@{$host}";
            $fromname = 'ムビチケ動画管理システム';

            // 改行コードをセット
            $mime = new Mail_mime("\n");
            require_once __DIR__ . '/templates/mail_finish_job.php';
            $mime->setHTMLBody($template);

            // 添付画像
//             $img  = __DIR__ . '/test.JPG';
//             $mime->addHTMLImage($img, 'image/jpeg');

            $body_param = array(
                "head_charset" => "ISO-2022-JP",
                "text_charset" => "ISO-2022-JP",
                "html_charset" => "UTF-8",
            );
            $body = $mime->get($body_param);

            $headers = array(
                'To'      => $to,
//                 'Cc'      => $cc,
//                 'Bcc'     => $bcc,
                'From'    => mb_encode_mimeheader($fromname) . '<' . $from . '>',
                'Subject' => mb_encode_mimeheader($subject)
            );
            $header = $mime->headers($headers);

            // 送信
            $mail = Mail::factory('mail');
            $return = $mail->send($to, $header, $body);
            if (PEAR::isError($return)) {
                $this->logger->log('sendEmail fail. $message:' . print_r($return->getMessage(), true));
            }

            /*
            $subject = '[ムビチケ動画管理システム]ストリーミングURLが発行されました';
            $message = "https://{$host}/media/{$mediaCode}";
            $headers = "From: webmaster@{$host}" . "\r\n"
                     . "Reply-To: webmaster@{$host}";
            if (!mail($email, $subject, $message, $headers)) {
                $this->logger->log('sendEmail fail. $message:' . print_r($message, true));
            }
            */
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * エラー通知メールを送信する
     *
     * @param string $to
     * @param string $message
     * @return none
     */
    public function sendErrorMail($to, $message)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->debug('args: ' . print_r(func_get_args(), true));

        // 送信
        if ($to) {
            // 言語設定、内部エンコーディング指定
            mb_language('japanese');
            mb_internal_encoding('UTF-8');

            $host = self::$host;
            $subject = '[ムビチケ動画管理システム]エラー通知';
            $from = "webmaster@{$host}";
            $fromname = 'ムビチケ動画管理システム';

            // 改行コードをセット
            $mime = new Mail_mime("\n");
            require_once __DIR__ . '/templates/mail_error.php';
            $mime->setHTMLBody($template);

            $body_param = array(
                "head_charset" => "ISO-2022-JP",
                "text_charset" => "ISO-2022-JP",
                "html_charset" => "UTF-8",
            );
            $body = $mime->get($body_param);

            $headers = array(
                'To'      => $to,
                'From'    => mb_encode_mimeheader($fromname) . '<' . $from . '>',
                'Subject' => mb_encode_mimeheader($subject)
            );
            $header = $mime->headers($headers);

            // 送信
            $mail = Mail::factory('mail');
            $return = $mail->send($to, $header, $body);
            if (PEAR::isError($return)) {
                $this->logger->log('sendErrorMail fail. $message:' . print_r($return->getMessage(), true));
            }
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * メディアのジョブ情報&タスクをリセットする
     *
     * @param array $mediaIds
     * @return boolean
     */
    public function resetMedias($mediaIds)
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
    public function deleteOutputAssets($jobId)
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
