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
     * タスク名 "MPEG-DASH"
     *
     * @var string
     */
    const NAME_MPEG_DASH = 'mpeg_dash';

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
     * タスク名 "MPEG-DASH(PlayReady保護)"
     *
     * @var string
     */
    const NAME_MPEG_DASH_PLAYREADY = 'mpeg_dash_playready';

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

    /**
     * タスクに対応するアセット名を取得する
     *
     * @param string $mediaId
     * @param string $taskName
     * @return string
     */
    public static function toAssetName($mediaId, $taskName)
    {
        return $mediaId . '[' . $taskName . ']';
    }
}
