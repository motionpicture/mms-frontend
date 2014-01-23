<?php
namespace MvtkService;

/**
 * MvtkService Autoloader
 * 
 * @package MvtkService
 */
// class Autoloader
// {
//     public static function load($class)
//     {
//         $filename = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

//         if (file_exists($filename)) {
//             include $filename;
//         }
//     }
// }

spl_autoload_register(function ($class)
{
    $filename = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

    if (file_exists($filename)) {
        include $filename;
    }
});
