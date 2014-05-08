<?php
if ($app->config('debug')) {
    echo '<pre>';
    print_r($response);
    echo '</pre>';
} else {
    echo json_encode($response);
}
?>