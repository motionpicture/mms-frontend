<?php
require_once('MmsDb.php');

require_once(dirname(__FILE__) . '/../lib/WindowsAzureMediaServices/WindowsAzureMediaServicesContext.php');

class MmsSlim extends \Slim\Slim
{
    public $db;
    public $mediaContext;
    public $categories;

    /**
     * Constructor
     * @param  array $userSettings Associative array of application settings
     */
    public function __construct(array $userSettings = array())
    {
        parent::__construct($userSettings);

        $this->db = new MmsDb();

        // カテゴリーを取得
        $categories = array();
        try {
            $query = 'SELECT * FROM category';
            $result = $this->db->query($query);
            while($res = $result->fetchArray(SQLITE3_ASSOC)){
                $categories[$res['id']] = $res['name'];
            }
        } catch (Exception $e) {
            $this->log($e);

            throw($e);
        }
        $this->categories = $categories;

        // DBにベーシック認証ユーザーが存在しなかれば登録
        $query = sprintf('SELECT * FROM user WHERE id = \'%s\';',
                        $_SERVER['PHP_AUTH_USER']);
        $user = $this->db->querySingle($query, true);
        if (!isset($user['id'])) {
            $query = sprintf("INSERT INTO user (id, created_at, updated_at) VALUES ('%s', datetime('now'), datetime('now'))",
                            $_SERVER['PHP_AUTH_USER']);
            $this->log->debug($query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }
        }

        $this->log->debug(print_r($_SERVER, true));
    }

}
?>