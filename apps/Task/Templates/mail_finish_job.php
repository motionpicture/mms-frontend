<?php
$template = <<<HTML
    <html>
    <head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <style>
    table {
        border: 1px #E3E3E3 solid;
        border-collapse: collapse;
        border-spacing: 0;
    }
    table th {
        padding: 5px;
        border: #E3E3E3 solid;
        border-width: 0 0 1px 1px;
        font-weight: bold;
    }
    table td {
        padding: 5px;
        border: 1px #E3E3E3 solid;
        border-width: 0 0 1px 1px;
    }
    </style>
    </head>
    <body>
        <h3>ストリーミングURLが発行されました</h3>
        <table>
            <tbody>
                <tr>
                    <th>ID</th>
                    <td>{$media['id']}</td>
                </tr>
                <tr>
                    <th>ユーザー</th>
                    <td>{$media['user_id']}</td>
                </tr>
                <tr>
                    <th>作品名</th>
                    <td>{$media['movie_name']}</td>
                </tr>
            </tbody>
        </table>
        <p>詳細は<a href="{$url}">コチラ</a></p>
    </body>
    </html>
HTML;
?>