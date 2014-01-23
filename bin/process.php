<?php
$filepath = fgets(STDIN);

file_put_contents('process_log', $filepath, FILE_APPEND);
