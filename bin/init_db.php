<?php
require_once('BaseContext.php');
$context = new \Mms\Bin\BaseContext();

try {
    $query = file_get_contents(dirname(__FILE__) . '/../db/initialize.sql');
    $context->db->exec($query);
} catch (Exception $e) {
    $context->log($e->getMessage());
}

?>