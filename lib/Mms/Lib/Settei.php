<?php
namespace Mms\Lib;

class Settei
{
    private static $instance = null;
    private $mode;
    private $dev = false;
    private $test = false;
    private $stg = false;
    private $prod = false;
    private $values = array();

    /**
     * constructor
     *
     * あえてのprivate constructor(このクラスのインスタンスを取得する際には、getInstanceを使用する)
     */
    private function __construct()
    {
        $this->initialize();
    }

    private function initialize()
    {
        // 環境
        include __DIR__ . '/../../../mode.php';
        $this->mode = $mode;
        $this->dev = ($mode === 'development');
        $this->test = ($mode === 'test');
        $this->stg = ($mode === 'staging');
        $this->prod = ($mode === 'production');

        // 設定ファイルを取得
//         $allModeSettei = yaml_parse_file(__DIR__ . '/../../../config/settei.yml');
        $allModeSettei = parse_ini_file(__DIR__ . '/../../../config/settei.ini', true);
        $this->values = $allModeSettei[$mode];
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function deleteInstance()
    {
        if (self::$instance) {
            self::$instance = null;
        }

        return self::$instance;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function isDev()
    {
        return $this->dev;
    }

    public function isTest()
    {
        return $this->test;
    }

    public function isStg()
    {
        return $this->stg;
    }

    public function isProd()
    {
        return $this->prod;
    }

    public function getValues()
    {
        return $this->values;
    }

    /**
     * $keyの設定値を取得する
     * 
     * @param string $key
     * @return string
     */
    public function get($key)
    {
        if (isset($this->values[$key])) {
            return $this->values[$key];
        }

        return null;
    }
}
