<?php
namespace Mms\Task\Controllers;

use \PEAR;
use \Mail;
use \Mail_mime;

ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

class BaseController
{
    public $config;
    public $logger;
    public $azureContext;
    public $db;
    public $executedAt; // タスクの実行時刻(Unix タイムスタンプ)
    public $host;

    public function __construct()
    {
        $this->config = \Mms\Lib\Settei::getInstance();
        $this->logger = \Mms\Lib\Logger::getInstance();
        $this->azureContext = \Mms\Lib\AzureContext::getInstance($this->config->getMode());
        $this->db = \Mms\Lib\PDO::getInstance();

        if ($this->config->isDev()) {
            $this->host = 'localhost';
        } else if ($this->config->isStg()) {
            $this->host = 'tmmediasvc.cloudapp.net';
        } else {
            $this->host = 'media.comovieticket.jp';
        }
    }

    /**
     * エラー通知
     *
     * @param string $message
     * @return none
     */
    function reportError($message)
    {
        $errorsIniArray = parse_ini_file(__DIR__ . '/../config/errors.ini', true);
        $errorsConfig = $errorsIniArray[$this->config->getMode()];

        $to = implode(',', $errorsConfig['to']);
        $subject = $errorsConfig['subject'];
        $headers = "From: webmaster@{$this->host}" . "\r\n"
                 . "Reply-To: webmaster@{$this->host}";
        if (!mail($to, $subject, $message, $headers)) {
            $this->logger->log('reportError fail. $message:' . print_r($message, true));
        }
    }

    /**
     * ストリームURL発行お知らせメールを送信する
     *
     * @param array $media
     * @return none
     */
    public function sendEmail($media)
    {
        $sql = "SELECT email FROM user WHERE id = '{$media['user_id']}'";
        $statement = $this->db->query($sql);
        $email = $statement->fetchColumn();

        // 送信
        if ($email) {
            $to = $email;
            $subject = '[ムビチケ動画管理システム]ストリーミングURLが発行されました';
            if (!$this->config->isProd()) {
                $subject = "[{$this->config->getMode()}]{$subject}";
            }
            $from = "webmaster@{$this->host}";
            $fromname = 'ムビチケ動画管理システム';

            // 本文を生成
            $url = "https://{$this->host}/media/{$media['code']}";
            require __DIR__ . '/../Templates/mail_finish_job.php';
            $body = $template;

            if ($this->config->isStg() || $this->config->isProd()) {
                $transport = \Swift_SmtpTransport::newInstance('localhost', 25);
            } else if ($this->config->isDev()) {
                $transport = \Swift_SmtpTransport::newInstance()
                    ->setHost('smtp.googlemail.com')
                    ->setPort(465)
                    ->setEncryption('ssl')
                    ->setUsername('yamazaki@motionpicture.jp')
                    ->setPassword('uhbnmj12');
            }

            $mailer = \Swift_Mailer::newInstance($transport);
            $message = \Swift_Message::newInstance($subject)
                ->setFrom([$from => $fromname])
                ->setTo($to)
                ->setBody($body, 'text/html');
            $mailer->send($message);
        }
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
        // 送信
        if ($to) {
            $errorsIniArray = parse_ini_file(__DIR__ . '/../../../config/errors.ini', true);
            $errorsConfig = $errorsIniArray[$this->config->getMode()];

            $subject = $errorsConfig['subject'];
            $from = "webmaster@{$this->host}";
            $fromname = $errorsConfig['fromname'];

            // 本文
            require __DIR__ . '/../Templates/mail_error.php';
            $body = $template;

            if ($this->config->isStg() || $this->config->isProd()) {
                $transport = \Swift_SmtpTransport::newInstance('localhost', 25);
            } else if ($this->config->isDev()) {
                $transport = \Swift_SmtpTransport::newInstance()
                    ->setHost('smtp.googlemail.com')
                    ->setPort(465)
                    ->setEncryption('ssl')
                    ->setUsername('yamazaki@motionpicture.jp')
                    ->setPassword('uhbnmj12');
            }

            $mailer = \Swift_Mailer::newInstance($transport);
            $message = \Swift_Message::newInstance($subject)
                ->setFrom([$from => $fromname])
                ->setTo($to)
                ->setBody($body, 'text/html');
            $mailer->send($message);
        }
    }

    /**
     * メディアのジョブ情報&タスクをリセットする
     *
     * @param array $mediaIds
     * @return boolean
     */
    public function resetMedias($mediaIds)
    {
        $this->logger->log('start function: ' . __FUNCTION__);
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

        $this->logger->log('end function: ' . __FUNCTION__);

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
        $this->logger->log('start function: ' . __FUNCTION__);
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

        $this->logger->log('end function: ' . __FUNCTION__);

        return $isDeleted;
    }
}
