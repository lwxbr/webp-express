<?php

//include_once __DIR__ . '/classes/Config.php';
//use \WebPExpress\Config;

include_once __DIR__ . '/classes/Paths.php';
use \WebPExpress\Paths;

use \WebPExpress\Option;

/* helper. Remove dir recursively. No warnings - fails silently */
function webpexpress_rrmdir($dir) {
   if (@is_dir($dir)) {
     $objects = @scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (@is_dir($dir."/".$object))
           webpexpress_rrmdir($dir."/".$object);
         else
           @unlink($dir."/".$object);
       }
     }
     @rmdir($dir);
   }
}

$optionsToDelete = [
    'webp-express-messages-pending',
    'webp-express-action-pending',
    'webp-express-state',
    'webp-express-version',
    'webp-express-activation-error',
    'webp-express-migration-version'
];
foreach ($optionsToDelete as $i => $optionName) {
    Option::deleteOption($optionName);
}

// remove content dir (config plus images plus htaccess-tests)
webpexpress_rrmdir(Paths::getWebPExpressContentDirAbs());
