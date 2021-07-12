<?php

spl_autoload_register(function($classname){
    $classnameForPath = implode(DIRECTORY_SEPARATOR,explode('\\', $classname));
    $file_path = __DIR__.DIRECTORY_SEPARATOR.$classnameForPath.'.php';
    if(file_exists($file_path)){
         require $file_path;
    }
});