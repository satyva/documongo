<?php

namespace documongo;

/*This project's namespace structure is leveraged to autoload requested classes at runtime.*/
function Load($class) {
    $file = __DIR__ . "/../" . str_replace("\\", DIRECTORY_SEPARATOR, $class) . ".php";

    if(is_file($file))
        include_once $file;
}
spl_autoload_register("documongo\Load");
if(in_array("__autoload", spl_autoload_functions()))
    spl_autoload_register("__autoload");


