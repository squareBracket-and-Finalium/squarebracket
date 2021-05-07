<?php 
require($_SERVER['DOCUMENT_ROOT'] . '/lib/common.php');
header("Content-type: text/css; charset: UTF-8");
use ScssPhp\ScssPhp\Compiler;

if(!isset($_GET['path'])) {
	die();
}

$scss = new Compiler();
$scss->setImportPaths($_SERVER['DOCUMENT_ROOT']);
$path = (isset($_GET['path']) ? sanitizeFilePath($_GET['path']) : null);
$cssfile = sanitizeFilePath('cache/'.substr($path, 0, strlen($path) - 4).'css');
if (file_exists($cssfile) AND $tplNoCache != true) {
    echo file_get_contents($cssfile);
} else {
    $css = $scss->compile('@import "'.$path.'"');
    if ($tplNoCache != true) {
        $parts = explode('/', $cssfile);
        array_pop($parts);
        $dir = implode('/', $parts);
        if (!is_dir($dir))
            mkdir($dir);
        $file = fopen($cssfile, 'w');
        fwrite($file, $css);
        fclose($file);
    }
    echo $css;
}

function sanitizeFilePath($path) {
	$path = str_replace('\'','',$path);
	$path = str_replace('`','',$path);
	$path = str_replace('/..','',$path);
	$path = str_replace('..','',$path);
	$path = str_replace('./','',$path);
	$path = str_replace(':','',$path);
	if (substr($path,0,1) == '/') 
		$path = substr($path,1);
	return $path;
}