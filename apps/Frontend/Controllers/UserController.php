<?php
namespace Mms\Frontend\Controllers;

class UserController extends BaseController
{
    public function edit()
    {
        $message = null;

        if ($this->app->request()->isPost()) {
            $defaults = $_POST;

            if (!$_POST['email']) {
                $message .= '<br>メールアドレスを入力してください';
            }

            if ($message) {
                return $this->app->render(
                    'user/edit.php',
                    [
                        'message' => $message,
                        'defaults' => $defaults
                    ]
                );
            }

            try {
                $email = $this->pdo->quote($_POST['email']);
                $query = "UPDATE user SET email = {$email}, updated_at = datetime('now', 'localtime') WHERE id = '{$_SERVER['PHP_AUTH_USER']}'";
                $this->app->log->debug('$query:' . $query);
                $this->pdo->exec($query);
            } catch (\Exception $e) {
                throw $e;
            }

            $this->app->redirect('/user/edit', 303);
        }

        $query = "SELECT email FROM user WHERE id = '{$_SERVER['PHP_AUTH_USER']}'";
        $statement = $this->pdo->query($query);
        $email = $statement->fetchColumn();

        $defaults = [
            'email' => $email,
        ];

        return $this->app->render(
            'user/edit.php',
            [
                'message' => $message,
                'defaults' => $defaults
            ]
        );
    }
}
