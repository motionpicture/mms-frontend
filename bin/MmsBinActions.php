<?php
ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once('MmsDb.php');

require_once(dirname(__FILE__) . '/../lib/WindowsAzureMediaServices/WindowsAzureMediaServicesContext.php');

class MmsBinActions
{
    public $db;
    public $mediaContext;
    public $logFile;

    function __construct()
    {
        $this->db = new MmsDb();

        $this->mediaContext = new WindowsAzureMediaServicesContext(
            'testmvtkms',
            'Vi3fX70rZKrtk/DM6TRoJ/XpxmkC29LNOzWimE06rx4=',
            null,
            null
        );

        $this->logFile = dirname(__FILE__) . '/mms_bin.log';
    }

    function log($content)
    {
        file_put_contents($this->logFile, print_r($content, true) . "\n", FILE_APPEND);
    }

    function debug($content)
    {
        echo '<pre>';
        print_r($content);
        echo '</pre>';
    }
}
?>
