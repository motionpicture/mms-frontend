<?php

namespace Mms\Lib;

use WindowsAzure\MediaServices\Models\Job;

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
        if ((string)$state === (string)Job::STATE_QUEUED) {
           return '待機中';
        } else if ((string)$state === (string)Job::STATE_SCHEDULED) {
            return 'スケジュール済み';
        } else if ((string)$state === (string)Job::STATE_PROCESSING) {
            return '進行中';
        } else if ((string)$state === (string)Job::STATE_FINISHED) {
            return '完了';
        } else if ((string)$state === (string)Job::STATE_ERROR) {
           return 'エラー';
        } else if ((string)$state === (string)Job::STATE_CANCELED) {
            return 'キャンセル済み';
        } else if ((string)$state === (string)Job::STATE_CANCELING) {
            return 'キャンセル中';
        } else {
            return '未登録';
        }
    }

    public static function isFinished($state)
    {
        return ((string)$state === (string)Job::STATE_FINISHED);
    }
}
