<?php

namespace Mms\Lib\Models;

/**
 * 動画管理システムタスクモデル
 *
 * @package   Mms\Lib\Models
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class Task
{
    /**
     * タスク名 "複数ビットレートMP4"
     *
     * @var string
     */
    const NAME_ADAPTIVE_BITRATE_MP4 = 'adaptive_bitrate_mp4';

    /**
     * タスク名 "スムーズストリーミング"
     *
     * @var string
     */
    const NAME_SMOOTH_STREAMING = 'smooth_streaming';

    /**
     * タスク名 "Http Live Streaming"
     *
     * @var string
     */
    const NAME_HLS = 'http_live_streaming';

    /**
     * タスク名 "スムーズストリーミング(PlayReady保護)"
     *
     * @var string
     */
    const NAME_SMOOTH_STREAMING_PLAYREADY = 'smooth_streaming_playready';

    /**
     * タスク名 "Http Live Streaming(PlayReady保護)"
     *
     * @var string
     */
    const NAME_HLS_PLAYREADY = 'http_live_streaming_playready';

    private $_mediaId;
    private $_name;
    private $_url;
    private $_createdAt;
    private $_updatedAt;
}
