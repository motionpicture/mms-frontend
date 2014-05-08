<?php
namespace Mms\Api\Lib;

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../../../vendor/autoload.php';

spl_autoload_register(function ($class) {
    require_once dirname(__FILE__) . '/../../../lib/' . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
});

class Slim extends \Slim\Slim
{
    public $db;

    /**
     * Constructor
     * @param  array $userSettings Associative array of application settings
     */
    public function __construct(array $userSettings = array())
    {
        // 環境取得
        $modeFile = dirname(__FILE__) . '/../../../mode.php';
        if (false === is_file($modeFile)) {
            exit('The application "mode file" does not exist.');
        }
        require_once($modeFile);
        if (empty($mode)) {
            exit('The application "mode" does not exist.');
        }

        $userSettings['mode'] = $mode;

        // デバッグモード
        if ($mode == 'development') {
            $userSettings['debug'] = true;
        } else {
            $userSettings['debug'] = false;
        }

        parent::__construct($userSettings);

        $this->db = \Mms\Lib\PDO::getInstance($userSettings['mode']);

        if ($this->config('debug')) {
            $this->response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        } else {
            $this->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        }
    }

    public function output($status, $message, $options = [])
    {
        $response = [
            'result' => [
                'status'  => $status,
                'message' => $message
            ]
        ];

        $response = array_merge($response, $options);

        return $this->render(
            'json.php',
            [
                'response' => $response
            ]
        );
    }
}
?>