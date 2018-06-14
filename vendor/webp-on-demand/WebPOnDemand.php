<?php
/*
URL parameters:

source: Path to source file.
    Can be absolute or relative to $root, that is passed in
    If it starts with "/", it is considered an absolute path.

destination-root (optional):
    The final destination will be calculated like this:
        [destination-root] + [relative path of source file] + ".webp".

    - Both absolute paths and relative paths are accepted (if the path starts with "/", it is considered an absolute
      path).
    - Double-dots in paths are allowed, ie "../webp-cache"

    If you want converted files to be put in the same folder as the originals, you can set destination-root to ".", or
    leave it blank. If you on the other hand want all converted files to reside in their own folder, set the
    destination-root to point to that folder. The converted files will be stored in a hierarchy that matches the source
    files. With destination-root set to "webp-cache", the source file "images/2017/cool.jpg" will be stored at
    "webp-cache/images/2017/cool.jpg.webp".

quality (optional):
    The quality of the generated WebP image, 0-100.

metadata (optional):
    If set to "none", all metadata will be stripped
    If set to "all", all metadata will be preserved
    Note however that not all converters supports preserving metadata. cwebp supports it, imagewebp does not.

converters (optional):
    Comma-separated list of converters. Ie. "cwebp,gd".
    To pass options to the individual converters, see next.
    Also, check out the WebPConvert docs

[converter-id]-[option-name] (optional):
    This pattern is used for setting options on the individual converters.
    Ie, in order to set the "key" option of the "ewww" converter, you pass "ewww-key".

[converter-id]-[n]-[option-name] (optional):
    Use this pattern for targeting options of a converter, that are used multiple times. However, use the pattern above
    for targeting the first occurence. `n` stands for the nth occurence of that converter in the `converters` option.
    Example: `...&converters=cwebp,ewww,ewww,gd,ewww&ewww-key=xxx&ewww-2-key=yyy&ewww-3-key=zzz&gd-skip-pngs=1`

converters (optional):
    Comma-separated list of converters. Ie. "cwebp,gd".
    Passing options to the individual converters is done by passing options named like this:
    [converter-name]-[option-name] (see below)

    See WebPConvert documentation for more info

[converter]-[option-name] (optional):
    Options for the converters can be passed as parameters with names like this: [converter]-[option-name].
    Ie, in order to set the "key" option of the "ewww" converter, you pass "ewww-key".

    If the same converter is going to be used with different configurations, you can add "-[n]" after the converter id.
    Ie: ...&converters=ewww,ewww&ewww-key=xxx&ewww-2-key=yyy

    See WebPConvert documentation for more info

debug (optional):
    If set, a report will be served (as text) instead of an image

fail:
   Default:  "original"
   What to serve if conversion fails

   Possible values:
   - "original":        Serves the original image (source)
   - "404":             Serves a 404 header
   - "text":            Serves the error message as plain text
   - "error-as-image":  Serves the error message as an image

critical-fail:
  Default:  "error-as-image"
  What to serve if conversion fails and source image is not available

  Possible values:
  - "404":             Serves a 404 header
  - "text":            Serves the error message as plain text
  - "error-as-image":  Serves the error message as an image

*/

namespace WebPOnDemand;

use WebPConvertAndServe\WebPConvertAndServe;
use WebPConvert\WebPConvert;
use WebPOnDemand\PathHelper;
use WebPConvert\Converters\ConverterHelper;

class WebPOnDemand
{
    public static function serve($root)
    {

        $debug = (isset($_GET['debug']) ? ($_GET['debug'] != 'no') : false);

        //$source = $root . '/' . $_GET['source'];
        $source = PathHelper::abspath($_GET['source'], $root);

        $source = PathHelper::removeDoubleSlash($source);


        if (isset($_GET['destination-root'])) {
            $destination = PathHelper::getDestinationPath($source, $_GET['destination-root'], $root);
        } else {
            $destination = $source . '.webp';
        }

        $options = [];

        // quality
        if (isset($_GET['quality'])) {
            $options['quality'] = $_GET['quality'];
        }

        // metadata
        if (isset($_GET['metadata'])) {
            $options['metadata'] = $_GET['metadata'];
        }

        // converters
        if (isset($_GET['converters'])) {
            $conv = explode(',', $_GET['converters']);
            $options['converters'] = [];
            foreach ($conv as $i => $converter_name) {
                $options['converters'][] = ['converter' => $converter_name, 'options' => []];
            }
        } else {
            // Copy default converters.
            // We need them in case some has options
            foreach (ConverterHelper::$defaultOptions['converters'] as $i => $converter_name) {
                $options['converters'][] = ['converter' => $converter_name, 'options' => []];
            }
        }


        // Converter options
        $counts = [];
        foreach ($options['converters'] as $i => $converter_object) {
            $converter = $converter_object['converter'];
            //echo $i . ':' . $converter;
            if (!isset($counts[$converter])) {
                $counts[$converter] = 1;
                $id = $converter;
            }
            else {
                $counts[$converter]++;
            }
            /*
            $availOptions = [];
            switch ($converter) {
                case 'ewww':
                    $availOptions = [
                        'key' => 'string-sensitive',
                    ];
                    break;
                case 'gd':
                    $availOptions = [
                        'skip-pngs' => 'boolean',
                    ];
                    break;
                case 'cwebp':
                    $availOptions = [
                        'use-nice' => 'boolean',
                    ];
                    break;
                case 'wpc':
                    $availOptions = [
                        'url' => 'string-sensitive',
                        'secret' => 'string-sensitive',
                    ];
                    break;

            }*/
            //print_r($availOptions);
            //echo '<br>';
            //print_r(\WebPConvert\Converters\Cwebp::$extraOptions);
            //$converterId = 'cwebp';
            $className = 'WebPConvert\\Converters\\' . ucfirst($converter);
            $availOptions = array_column($className::$extraOptions, 'type', 'name');
            //print_r($availOptions);

            foreach ($availOptions as $optionName => $optionType) {
                $parameterName = $converter . (($counts[$converter] > 1 ? '-' . $counts[$converter] : '')) . '-' . $optionName;
                switch ($optionType) {
                    case 'string':
                        if (isset($_GET[$parameterName])) {
                            //echo $parameterName . ':' . $_GET[$parameterName] . '<br>';
                            $options['converters'][$i]['options'][$optionName] = $_GET[$parameterName];
                        }
                        break;
                    case 'boolean':
                        if (isset($_GET[$parameterName])) {
                            $options['converters'][$i]['options'][$optionName] = ($_GET[$parameterName] == '1');
                        }
                        break;
                }
            }
        }

        // Failure actions
        $failCodes = [
            "original" => WebPConvertAndServe::$ORIGINAL,
            "404" => WebPConvertAndServe::$HTTP_404,
            "error-as-image" => WebPConvertAndServe::$REPORT_AS_IMAGE,
            "report" => WebPConvertAndServe::$REPORT,
        ];

        $fail = 'original';
        if (isset($_GET['fail'])) {
            $fail = $_GET['fail'];
        }
        $fail = $failCodes[$fail];

        $criticalFail = 'report';
        if (isset($_GET['critical-fail'])) {
            $criticalFail = $_GET['critical-fail'];
        }
        $criticalFail = $failCodes[$criticalFail];

        if (!$debug) {
            return WebPConvertAndServe::convertAndServeImage($source, $destination, $options, $fail, $criticalFail);
        } else {
            echo 'GET parameters:<br>';
            // TODO!!!
            // Do not leak api keys!
            // Right now, you can see all options, including api keys, by appending "?debug" after an image URL!
            foreach ($_GET as $key => $value) {
                echo '<i>' . $key . '</i>: ' . htmlspecialchars($value) . '<br>';
            }
            echo '<br>';
            //echo $_SERVER['DOCUMENT_ROOT'];
            WebPConvertAndServe::convertAndReport($source, $destination, $options);
            return 1;
        }
    }
    /*
$root = (
    isset($_GET['root-folder']) ?
        PathHelper::removeDoubleSlash($_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['root-folder']) :
        null
);*/
/*
destination (optional): (TODO)
    Path to destination file. Can be absolute or relative (relative to document root).
    You can choose not to specify destination. In that case, the path will be created based upon source,
    destination-root and root-folder settings. If all these are blank, the destination will be same folder as source,
    and the filename will have ".webp" appended to it (ie image.jpeg.webp)

root-folder (optional):
    Usually, you will not need to supply anything. Might be relevant in rare occasions where the converter that
    generates the URL cannot pass all of the relative path. For example, an .htaccess located in a subfolder may have
    trouble passing the parent folders.*/
//$source = PathHelper::abspath($_GET['source'], $root);*/
}