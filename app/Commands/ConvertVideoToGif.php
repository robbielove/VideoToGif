<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ConvertVideoToGif1 extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'app:convert-video-to-gif1 {inputDir} {--outputDir=./output_gifs}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Convert videos in a directory to GIFs with subtitles';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $inputDir = $this->argument('inputDir');
        $outputDir = $this->option('outputDir');

        if (!is_dir($inputDir)) {
            $this->error("Input directory {$inputDir} does not exist.");
            return;
        }

        $videos = glob($inputDir . '/*.mp4');  // assuming mp4 format, adjust if needed

        foreach ($videos as $video) {
            $filename = pathinfo($video, PATHINFO_FILENAME);
            $subtitleFiles = glob($inputDir . "/*.{srt,ass,ssa}", GLOB_BRACE);
            $subtitleFile = $this->getSubtitleFile($filename, $subtitleFiles);

            $targetDir = "{$outputDir}/{$filename}";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            } else {
                array_map('unlink', glob("{$targetDir}/*.gif"));
            }

            $subtitles = [];
            if ($subtitleFile) {
                $subtitles = $this->parseSubtitles($subtitleFile);
            }

            if (!$subtitles) {
                $videoDuration = shell_exec("ffmpeg -i {$video} 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//");
                $durationInSeconds = $this->convertToSeconds($videoDuration);
                $subtitles = $this->generateDefaultSubtitles($durationInSeconds);
            }

            foreach ($subtitles as $index => $subtitle) {
                $start = str_replace(',', '.', $subtitle['start']);
                $end = str_replace(',', '.', $subtitle['end']);
                $text = $subtitle['text'];

                $outputGif = "{$targetDir}/{$filename}-{$index}.gif";
                $this->info("Generating GIF for {$start} to {$end}");
                $videoPath = escapeshellarg($video); // Escape the video path

                // Clean up subtitle text by stripping HTML tags and other special characters
                $cleanSubtitleText = escapeshellarg(html_entity_decode(strip_tags($subtitle['text'])));

                $cmd = "ffmpeg -ss {$start} -to {$end} -i {$videoPath} -vf \"drawtext=text={$cleanSubtitleText}:x=(w-text_w)/2:y=h-th-10:fontsize=24:fontcolor=white\" -y {$outputDir}/{$filename}/{$filename}-{$index}.gif";

                shell_exec($cmd);
            }
        }

        $this->info("All videos processed!");
    }

    private function getSubtitleFile($videoFilename, $subtitleFiles) {
        $preferredSubtitles = ['eng', 'en', 'english'];
        $matchingSubtitles = array_filter($subtitleFiles, function ($subtitle) use ($videoFilename) {
            return strpos($subtitle, $videoFilename) !== false;
        });

        if ($matchingSubtitles) {
            return array_shift($matchingSubtitles);
        }

        foreach ($preferredSubtitles as $preferredSubtitle) {
            foreach ($subtitleFiles as $subtitleFile) {
                if (strpos($subtitleFile, $preferredSubtitle) !== false) {
                    return $subtitleFile;
                }
            }
        }

        return $subtitleFiles[0] ?? null;
    }

    private function parseSubtitles($subtitleFile) {
        $contents = file_get_contents($subtitleFile);
        $lines = array_map('trim', explode("\n", $contents));

        $subtitles = [];
        $buffer = [];

        foreach ($lines as $line) {
            if (empty($line) && !empty($buffer)) {
                if (preg_match("/(\d{2}:\d{2}:\d{2},\d{3}) --> (\d{2}:\d{2}:\d{2},\d{3})/", $buffer[1], $matches)) {
                    $subtitles[] = [
                        'start' => $matches[1],
                        'end' => $matches[2],
                        'text' => implode(' ', array_slice($buffer, 2))
                    ];
                }
                $buffer = [];
            } else {
                $buffer[] = $line;
            }
        }

        return $subtitles;
    }

    private function convertToSeconds($time) {
        if (count(explode(":", $time)) !== 3) {
            $this->error("Unexpected time format: " . $time);
            return 0;
        }
        list($hours, $minutes, $seconds) = explode(":", $time);
        return $hours * 3600 + $minutes * 60 + floatval($seconds);
    }

    private function generateDefaultSubtitles($duration) {
        $subtitles = [];

        for ($i = 0; $i < $duration; $i += 10) {
            $subtitles[] = [
                'start' => gmdate("H:i:s", $i),
                'end' => gmdate("H:i:s", $i + 10),
                'text' => ''
            ];
        }

        return $subtitles;
    }
}
