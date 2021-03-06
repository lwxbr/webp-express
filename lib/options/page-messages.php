<?php

use \WebPExpress\Paths;
use \WebPExpress\HTAccess;
use \WebPExpress\Config;
use \WebPExpress\State;
use \WebPExpress\Messenger;
use \WebPExpress\PlatformInfo;
use \WebPExpress\FileHelper;
use \WebPExpress\CapabilityTest;
//include __DIR__ . "/page-welcome.php";

//echo 'display errors:' . ini_get('display_errors');

if ((!State::getState('configured', false))) {
    include __DIR__ . "/page-welcome.php";
}


/*
if (CapabilityTest::modRewriteWorking()) {
    echo 'mod rewrite works. that is nice';
}*/

/*if (CapabilityTest::modHeaderWorking() === true) {
    //echo 'nice!';
}*/
/*
if (CapabilityTest::copyCapabilityTestsToWpContent()) {
    echo 'copy ok!';
} else {
    echo 'copy failed!';
}*/



$anyRedirectionToConverterEnabled = (($config['enable-redirection-to-converter']) || ($config['enable-redirection-to-webp-realizer']));
$anyRedirectionEnabled = ($anyRedirectionToConverterEnabled || $config['redirect-to-existing-in-htaccess']);

if ($anyRedirectionEnabled) {
    if (PlatformInfo::definitelyNotGotModRewrite()) {
        Messenger::printMessage(
            'error',
            "Rewriting isn't enabled on your server. ' .
                'Currently, the only way to make WebP Express generate webp files is with rewriting. '
                'If you got the webp files through other means, you can use CDN friendly mode and disable the rewrites. ' .
                'Or perhaps you want to enable rewriting? Tell your host or system administrator to enable the 'mod_rewrite' module. ' .
                'If you are on a shared host, chances are that mod_rewrite can be turned on in your control panel."
        );
    }
}

$cacheEnablerActivated = in_array('cache-enabler/cache-enabler.php', get_option('active_plugins', []));
if ($cacheEnablerActivated) {
    $cacheEnablerSettings = get_option('cache-enabler', []);
    $webpEnabled = (isset($cacheEnablerSettings['webp']) && $cacheEnablerSettings['webp']);
}

if ($cacheEnablerActivated && !$webpEnabled) {
    Messenger::printMessage(
        'warning',
            'You are using Cache Enabler, but have not enabled the webp option, so Cache Enabler is not operating with a separate cache for webp-enabled browsers.'
    );
}

if (($config['operation-mode'] == 'cdn-friendly') && !$config['alter-html']['enabled']) {
    //echo print_r(get_option('cache-enabler'), true);


    if ($cacheEnablerActivated) {
        if ($webpEnabled) {
            Messenger::printMessage(
                'info',
                    'You should consider enabling Alter HTML. This is not neccessary, as you have <i>Cache Enabler</i> enabled, which alters HTML. ' .
                    'However, it is a good idea because currently <i>Cache Enabler</i> does not replace as many URLs as WebP Express (ie background images in inline styles)'
            );
        }

    } else {
        Messenger::printMessage(
            'warning',
                'You are in CDN friendly mode but have not enabled Alter HTML (and you are not using Cache Enabler either). ' .
                    'This is usually a misconfiguration because in this mode, the only way to get webp files is by referencing them in the HTML.'
        );

    }
}

if (!$anyRedirectionToConverterEnabled && ($config['operation-mode'] == 'cdn-friendly')) {
    // this can not happen in varied image responses. it is ok in no-conversion, and also tweaked, because one could wish to tweak the no-conversion mode
    Messenger::printMessage(
        'warning',
            'You have not enabled any of the redirects to the converter. ' .
                'At least one of the redirects is required for triggering WebP generation.'
    );
}

if ($config['alter-html']['enabled'] && !$config['alter-html']['only-for-webps-that-exists'] && !$config['enable-redirection-to-webp-realizer']) {
    Messenger::printMessage(
        'warning',
            'You have configured Alter HTML to make references to WebP files that are yet to exist, ' .
                '<i>but you have not enabled the option that makes these files come true when requested</i>. Do that!'
    );
}

if ($config['enable-redirection-to-webp-realizer'] && $config['alter-html']['enabled'] && $config['alter-html']['only-for-webps-that-exists']) {
    Messenger::printMessage(
        'warning',
            'You have enabled the option that redirects requests for non-existing webp files to the converter, ' .
                '<i>but you have not enabled the option to point to these in Alter HTML</i>. Please do that!'
    );
}


/*
if (Config::isConfigFileThereAndOk() ) { // && PlatformInfo::definitelyGotModEnv()
    if (!isset($_SERVER['HTACCESS'])) {
        Messenger::printMessage(
            'warning',
            "Using rewrite rules in <i>.htaccess</i> files seems to be disabled " .
                "(The <i>AllowOverride</i> directive is probably set to <i>None</i>. " .
                "It needs to be set to <i>All</i>, or at least <i>FileInfo</i> to allow rewrite rules in <i>.htaccess</i> files.)<br>" .
                "Disabled <i>.htaccess</i> files is actually a good thing, both performance-wise and security-wise. <br> " .
                "But it means you will have to insert the following rules into your apache configuration manually:" .
                "<pre>" . htmlentities(print_r(Config::generateHTAccessRulesFromConfigFile(), true)) . "</pre>"
        );
    }
}*/
if (!Paths::createContentDirIfMissing()) {
    Messenger::printMessage(
        'error',
        'WebP Express needs to create a directory "webp-express" under your wp-content folder, but does not have permission to do so.<br>' .
            'Please create the folder manually, or change the file permissions of your wp-content folder (failed to create this folder: ' . Paths::getWebPExpressContentDirAbs() . ')'
    );
} else {
    if (!Paths::createConfigDirIfMissing()) {
        Messenger::printMessage(
            'error',
            'WebP Express needs to create a directory "webp-express/config" under your wp-content folder, but does not have permission to do so.<br>' .
                'Please create the folder manually, or change the file permissions.'
        );
    }

    if (!Paths::createCacheDirIfMissing()) {
        Messenger::printMessage(
            'error',
            'WebP Express needs to create a directory "webp-express/webp-images" under your wp-content folder, but does not have permission to do so.<br>' .
                'Please create the folder manually, or change the file permissions.'
        );
    }
}

if (Config::isConfigFileThere()) {
    if (!Config::isConfigFileThereAndOk()) {
        Messenger::printMessage(
            'warning',
            'Warning: The configuration file is not ok! (cant be read, or not valid json).<br>' .
                'file: "' . Paths::getConfigFileName() . '"'
        );
    } else {
        if (HTAccess::arePathsUsedInHTAccessOutdated()) {
            Messenger::printMessage(
                'warning',
                'Warning: Wordpress paths have changed since the last time the Rewrite Rules was generated. The rules needs updating! (click <i>Save settings</i> to do so)<br>'
            );
        }
    }
}

$haveRulesInIndexDir = HTAccess::haveWeRulesInThisHTAccessBestGuess(Paths::getIndexDirAbs() . '/.htaccess');
$haveRulesInContentDir = HTAccess::haveWeRulesInThisHTAccessBestGuess(Paths::getContentDirAbs() . '/.htaccess');

if ($haveRulesInIndexDir && $haveRulesInContentDir) {
    // TODO: Use new method for determining if htaccess contains rules.
    // (either haveWeRulesInThisHTAccessBestGuess($filename) or haveWeRulesInThisHTAccess($filename))
    if (!HTAccess::saveHTAccessRulesToFile(Paths::getIndexDirAbs() . '/.htaccess', '# WebP Express has placed its rules in your wp-content dir. Go there.', false)) {
        Messenger::printMessage(
            'warning',
            'Warning: WebP Express have rules in both your wp-content folder and in your Wordpress folder.<br>' .
                'Please remove those in the <i>.htaccess</i> in your Wordress folder manually, or let us handle it, by granting us write access'
        );
    }
}

$ht = FileHelper::loadFile(Paths::getIndexDirAbs() . '/.htaccess');
if ($ht !== false) {
    $posWe = strpos($ht, '# BEGIN WebP Express');
    $posWo = strpos($ht, '# BEGIN WordPress');
    if (($posWe !== false) && ($posWo !== false) && ($posWe > $posWo)) {

        $haveRulesInIndexDir = HTAccess::haveWeRulesInThisHTAccessBestGuess(Paths::getIndexDirAbs() . '/.htaccess');
        if ($haveRulesInIndexDir) {
            Messenger::printMessage(
                'warning',
                'Problem detected. ' .
                    'In order for the "Convert non-existing webp-files upon request" functionality to work, you need to either:<br>' .
                    '- Move the WebP Express rules above the Wordpress rules in the .htaccess file located in your root dir<br>' .
                    '- Grant the webserver permission to your wp-content dir, so it can create its rules there instead.'
            );
        }
    }
}
