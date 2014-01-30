<?php
class MmsDb extends SQLite3
{
    function __construct()
    {
        $this->open(dirname(__FILE__) . '/../db/mms.db');
    }
}
?>