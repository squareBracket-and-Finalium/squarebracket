#!/usr/bin/php
<?php
namespace OpenSB;

global $ffmpegPath, $ffprobePath, $database, $orange;

use FFMpeg\Coordinate;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Filters;
use FFMpeg\Format\Video\X264;

// Fun fact:
// KamTape is using a heavily modified version of this script, given how their
// script has a mention of the string "Something went wrong!:", which originates
// from an earlier version of OpenSB's processing worker script.
//
// -Bluffingo March 1st 2024

define("SB_DYNAMIC_PATH", dirname(__DIR__, 2) . '/dynamic');
define("SB_PRIVATE_PATH", dirname(__DIR__, 2) . '/private');
define("SB_VENDOR_PATH", dirname(__DIR__, 2) . '/vendor');
define("SB_GIT_PATH", dirname(__DIR__, 2) . '/.git'); // ONLY FOR makeVersionString() IN Orange CLASS.

require_once SB_PRIVATE_PATH . '/class/common.php';

echo $orange->printVersionForOutput();

$config = [
    'timeout' => 3600, // The timeout for the underlying process (1 hour?)
    'ffmpeg.threads' => 12,   // The number of threads that FFmpeg should use
    'ffmpeg.binaries' => ($ffmpegPath ? $ffmpegPath : 'ffmpeg'),
    'ffprobe.binaries' => ($ffprobePath ? $ffprobePath : 'ffprobe'),
];

if (!isset($argv[1])) {
    die("No parameters have been specified.");
}

$new = $argv[1];
$target_file = $argv[2];

try {
    $ffmpeg = FFMpeg::create($config);
    $ffprobe = FFProbe::create($config);
    $h264 = new X264();

    $h264->setAudioKiloBitrate(320)->setAdditionalParameters(array('-ar', '44100'));

    $video = $ffmpeg->open($target_file);

    //get frame count
    $duration = $ffprobe
        ->streams($target_file)    // extracts file information
        ->videos()              // filters video streams
        ->first()               // returns the first video stream
        //->get('nb_frames');    // returns the duration property
        ->get('nb_read_frames');    // i think this might be slower?

    //get fractional framerate
    $fracFramerate = $ffprobe
        ->streams($target_file)    // extracts file information
        ->videos()              // filters video streams
        ->first()               // returns the first video stream
        ->get("avg_frame_rate");

    // get width
    $videoWidth = $ffprobe
        ->streams($target_file)    // extracts file information
        ->videos()              // filters video streams
        ->first()               // returns the first video stream
        ->get("width");

    // get height
    $videoHeight = $ffprobe
        ->streams($target_file)    // extracts file information
        ->videos()              // filters video streams
        ->first()               // returns the first video stream
        ->get("height");

    //get the actual framerate
    $framerate = explode("/", $fracFramerate)[0] / explode("/", $fracFramerate)[1];

    // Thumbnail
    //this doesn't scale too well with short videos.
    $seccount = round($duration / 4);
    $seccount2 = $seccount * 1.5;
    $frame = $video->frame(Coordinate\TimeCode::fromSeconds($seccount2 / $framerate));
    $frame->filters()->custom('scale=512x288');
    $frame->save(SB_DYNAMIC_PATH . '/thumbnails/' . $new . '.png');

    // Video

    $video->filters()->resize(new Coordinate\Dimension(1280, 720), Filters\Video\ResizeFilter::RESIZEMODE_INSET, true)
        ->custom('format=yuv420p');

    $video->save($h264, SB_DYNAMIC_PATH . '/videos/' . $new . '.converted.mp4');

    debug_print_backtrace();
    unlink($target_file);
    //delete_directory($preload_folder);

    $videoData = $database->fetch("SELECT v.* FROM videos v WHERE v.video_id = ?", [$new]);

    $database->query("UPDATE videos SET videolength = ?, flags = ? WHERE video_id = ?",
        [round($duration / $framerate), $videoData['flags'] ^ 0x2, $new]);
} catch (\Exception $e) {
    echo time() . " openSB Video Processing Worker Failure: " . $e->getMessage();
}

clearstatcache();