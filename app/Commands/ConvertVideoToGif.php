<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ConvertVideoToGif extends Command
{
    protected $signature = 'convert {inputDirectory} {outputDirectory}';
    protected $description = 'Convert video files to GIFs with overlaid subtitles.';

    public function handle()
    {
        $inputDirectory = $this->argument('inputDirectory');
        $outputDirectory = $this->argument('outputDirectory');

        $files = scandir($inputDirectory);
        $videoFiles = preg_grep("/\.(mp4|mkv|avi|mov)$/i", $files);

        foreach ($videoFiles as $videoFile) {
            $this->info("Processing {$videoFile}...");

            $filenameWithoutExtension = pathinfo($videoFile, PATHINFO_FILENAME);
            $outputDirForCurrentFile = $outputDirectory . $filenameWithoutExtension;

            if (file_exists($outputDirForCurrentFile)) {
                array_map('unlink', glob("$outputDirForCurrentFile/*.gif"));
            } else {
                mkdir($outputDirForCurrentFile, 0777, true);
            }

            $subtitleFile = null;
            foreach (["{$filenameWithoutExtension}.srt", "eng.srt", "en.srt", "english.srt"] as $potentialSubtitle) {
                if (file_exists($inputDirectory . '/' . $potentialSubtitle)) {
                    $subtitleFile = $potentialSubtitle;
                    break;
                }
            }

            $subtitleFilter = $subtitleFile ? "-vf subtitles='{$inputDirectory}/{$subtitleFile}'" : '';
            $ffmpegCommand = "ffmpeg -i {$inputDirectory}/{$videoFile} $subtitleFilter {$outputDirForCurrentFile}/{$filenameWithoutExtension}.gif";

            $this->info("Running command: $ffmpegCommand");
            system($ffmpegCommand);
        }

        $this->info("Processing finished.");
    }
}

