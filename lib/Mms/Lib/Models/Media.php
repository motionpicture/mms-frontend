<?php

namespace Mms\Lib\Models;

/**
 * 動画管理システムメディアモデル
 *
 * @package   Mms\Lib\Models
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class Media
{
    /**
     * ID生成時のセパレーター
     *
     * @var string
     */
    const ID_SEPARATOR = '_';

    private $_id;
    private $_code;
    private $_mcode;
    private $_categoryId;
    private $_version;
    private $_size;
    private $_extension;
    private $_userId;
    private $_movieName;
    private $_playtimeString;
    private $_playtimeSeconds;
    private $_jobId;
    private $_jobState;
    private $_jobStartAt;
    private $_jobEndAt;
    private $_startAt;
    private $_endAt;
    private $_createdAt;
    private $_updatedAt;

    /**
     * Create media from array
     *
     * @param array $options Array containing values for object properties
     *
     * @return Mms\Lib\Models\Media
     */
    public static function createFromOptions($options)
    {
        // TODO 必須項目チェック
//         Validate::notNull($options['TaskBody'], 'options[TaskBody]');
//         Validate::notNull($options['Options'], 'options[Options]');
//         Validate::notNull($options['MediaProcessorId'], 'options[MediaProcessorId]');

        $media = new Media(
            $options['mcode'],
            $options['categoryId'],
            $options['userId'],
            $options['extension'],
            $options['version']
        );
        $media->fromArray($options);

        return $media;
    }

    /**
     * Create media
     *
     * @param string $taskBody         Task body.
     * @param string $mediaProcessorId Media processor identifier.
     * @param int    $options          Task encryption options.
     */
    public function __construct($mcode, $categoryId, $userId, $extension, $version)
    {
        $this->_mcode         = $mcode;
        $this->_categoryId          = $categoryId;
        $this->_userId = $userId;
        $this->_extension = $extension;
        $this->_version = $version;
        $this->_code = implode(self::ID_SEPARATOR, array($mcode, $categoryId));
        $this->_id = implode(self::ID_SEPARATOR, array($this->_code, $version));
    }

    /**
     * Fill media from array
     *
     * @param array $options Array containing values for object properties
     *
     * @return none
     */
    public function fromArray($options)
    {
        if (isset($options['size'])) {
            $this->_size = $options['size'];
        }

        if (isset($options['movieName'])) {
            $this->_movieName = $options['movieName'];
        }

        if (isset($options['playtimeString'])) {
            $this->_playtimeString = $options['playtimeString'];
        }

        if (isset($options['playtimeSeconds'])) {
            $this->_playtimeSeconds = $options['playtimeSeconds'];
        }

        if (isset($options['jobId'])) {
          $this->_jobId = $options['jobId'];
        }

        if (isset($options['jobState'])) {
          $this->_jobState = $options['jobState'];
        }

        if (isset($options['jobStartAt'])) {
          $this->_jobStartAt = $options['jobStartAt'];
        }

        if (isset($options['jobEndAt'])) {
          $this->_jobEndAt = $options['jobEndAt'];
        }

        if (isset($options['startAt'])) {
          $this->_startAt = $options['startAt'];
        }

        if (isset($options['endAt'])) {
          $this->_endAt = $options['endAt'];
        }

        if (isset($options['createdAt'])) {
          $this->_createdAt = $options['createdAt'];
        }

        if (isset($options['updatedAt'])) {
          $this->_updatedAt = $options['updatedAt'];
        }
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setId($value)
    {
        $this->_id = $value;
    }

    public function getCode()
    {
        return $this->_code;
    }

    public function setCode($value)
    {
        $this->_code = $value;
    }

    public function getMcode()
    {
        return $this->_mcode;
    }

    public function setMcode($value)
    {
        $this->_mcode = $value;
    }

    public function getCategoryId()
    {
      return $this->_categoryId;
    }

    public function setCategoryId($value)
    {
      $this->_categoryId = $value;
    }

    public function getVersion()
    {
      return $this->_version;
    }

    public function setVersion($value)
    {
      $this->_version = $value;
    }

    public function getSize()
    {
      return $this->_size;
    }

    public function setSize($value)
    {
      $this->_size = $value;
    }

    public function getExtension()
    {
      return $this->_extension;
    }

    public function setExtension($value)
    {
      $this->_extension = $value;
    }

    public function getUserId()
    {
      return $this->_userId;
    }

    public function setUserId($value)
    {
      $this->_userId = $value;
    }

    public function getMovieName()
    {
      return $this->_movieName;
    }

    public function setMovieName($value)
    {
      $this->_movieName = $value;
    }

    public function getPlaytimeString()
    {
      return $this->_playtimeString;
    }

    public function setPlaytimeString($value)
    {
      $this->_playtimeString = $value;
    }

    public function getPlaytimeSeconds()
    {
      return $this->_playtimeSeconds;
    }

    public function setPlaytimeSeconds($value)
    {
      $this->_playtimeSeconds = $value;
    }

    public function getJobId()
    {
      return $this->_jobId;
    }

    public function setJobId($value)
    {
      $this->_jobId = $value;
    }

    public function getJobState()
    {
      return $this->_jobState;
    }

    public function setJobState($value)
    {
      $this->_jobState = $value;
    }

    public function getJobStartAt()
    {
      return $this->_jobStartAt;
    }

    public function setJobStartAt($value)
    {
      $this->_jobStartAt = $value;
    }

    public function getJobEndAt()
    {
      return $this->_jobEndAt;
    }

    public function setJobEndAt($value)
    {
      $this->_jobEndAt = $value;
    }

    public function getStartAt()
    {
      return $this->_startAt;
    }

    public function setStartAt($value)
    {
      $this->_startAt = $value;
    }

    public function getEndAt()
    {
      return $this->_endAt;
    }

    public function setEndAt($value)
    {
      $this->_endAt = $value;
    }

    public function getCreatedAt()
    {
      return $this->_createdAt;
    }

    public function setCreatedAt($value)
    {
      $this->_createdAt = $value;
    }

    public function getUpdatedAt()
    {
      return $this->_updatedAt;
    }

    public function setUpdatedAt($value)
    {
      $this->_updatedAt = $value;
    }
}
