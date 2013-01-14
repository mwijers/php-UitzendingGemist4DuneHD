<?php
require_once dirname(__FILE__).'/log.php';
require_once dirname(__FILE__).'/util.php';
require_once dirname(__FILE__).'/config.php';

global $publicMode;
if($publicMode)
{
    require_once dirname(__FILE__).'/lib_storage.php';
    registerMediaPlayer();
}

function writeItem($nr, $caption, $url, $action = 0)
{
	$caption = str_replace("\n", ' ', $caption);
	$caption = str_replace("\r", '', $caption);
	echo "item.$nr.caption = $caption\n";
	echo "item.$nr.media_url = $url\n";
	if($action)
		echo "item.$nr.media_action = $action\n";
}

function writeIcon($num, $caption, $url, $url_icon = 0)
{
	$caption = str_replace("\n", ' ', $caption);
	$caption = str_replace("\r", '', $caption);
	
    echo "item.$num.caption = $caption\n";
    if($url_icon)
        echo "item.$num.icon_path = $url_icon\n";
    echo "item.$num.scale_factor = 1\n";
    echo "item.$num.media_url = $url\n";    
}

function dunePlay($duneurl, $contentType)
{
	echo "# Dune play content-type: $contentType\n";
	echo "# Dune play streamurl:    $duneurl\n";

	if($contentType == 'video/mp4')
	{
		// Enable special Dune-HD MP4 video stream buffering
		$duneurl=str_replace('http://', 'http://mp4://', $duneurl);
	}

	echo "paint_scrollbar=no\n";
	echo "paint_path_box=no\n";
	echo "paint_help_line=no\n";
	echo "paint_icon_selection_box=no\n";
	echo "paint_icons=no\n";
	writeItem(0, 'Play', $duneurl);
}

function duneError($error)
{
	echo "paint_scrollbar=no\n";
	echo "paint_path_box=no\n";
	echo "paint_help_line=no\n";
	echo "paint_icon_selection_box=no\n";
	echo "paint_icons=no'\n";
	writeItem(0, 'ERROR: '.$error, '');
}

function getDuneSerial()
{
    $headers = apache_request_headers();
    return $headers['X-Dune-Serial-Number'];
}

function getDuneLang()
{
    $headers = apache_request_headers();
    return $headers['X-Dune-Interface-Language'];
}

?>