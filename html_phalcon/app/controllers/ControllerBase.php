<?php
// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../../../vendor/autoload.php';

use WindowsAzure\Common\Internal\MediaServicesSettings;
use WindowsAzure\MediaServices\Models\Job;

class ControllerBase extends Phalcon\Mvc\Controller
{

    protected function initialize()
    {
        //Prepend the application name to the title
//         $this->tag->prependTitle('INVO | ');
    }

    //...
}

class JobState
{
    public static function getAll()
    {
        return [
        Job::STATE_QUEUED,
        Job::STATE_SCHEDULED,
        Job::STATE_PROCESSING,
        Job::STATE_FINISHED,
        Job::STATE_ERROR,
        Job::STATE_CANCELED,
        Job::STATE_CANCELING
        ];
    }

    public static function toString($state)
    {
        if ($state == Job::STATE_QUEUED) {
            return '待機中';
        } else if ($state == Job::STATE_SCHEDULED) {
            return 'スケジュール済み';
        } else if ($state == Job::STATE_PROCESSING) {
            return '進行中';
        } else if ($state == Job::STATE_FINISHED) {
            return '完了';
        } else if ($state == Job::STATE_ERROR) {
            return 'エラー';
        } else if ($state == Job::STATE_CANCELED) {
            return 'キャンセル済み';
        } else if ($state == Job::STATE_CANCELING) {
            return 'キャンセル中';
        }
    }
}