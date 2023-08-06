<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ConvertVideoToGif extends Command
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

        $videos = glob($inputDir . '/*.{mp4,mkv,avi,flv,wmv,mov}', GLOB_BRACE);  // supporting multiple formats

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
                $escapedVideoPath = escapeshellarg($video);
                $videoDuration = shell_exec("ffmpeg -i {$escapedVideoPath} 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//");
                $output = shell_exec("ffmpeg -i {$escapedVideoPath} 2>&1");
                $this->info("ffmpeg output: {$output}");  // Check if ffmpeg outputs details
                $durationLine = shell_exec("echo '{$output}' | grep 'Duration'");
                $this->info("Duration line: {$durationLine}");  // Check if grep fetches the duration line
                $this->info("Fetched Duration: {$videoDuration}");  // Logging duration
                $durationInSeconds = $this->convertToSeconds($videoDuration);
                $subtitles = $this->generateDefaultSubtitles($durationInSeconds);
            }

            foreach ($subtitles as $index => $subtitle) {
                $start = str_replace(',', '.', $subtitle['start']);
                $end = str_replace(',', '.', $subtitle['end']);
                $text = $subtitle['text'];

                // Normalize subtitle text for filename
                $normalizedText = preg_replace("/[^A-Za-z0-9]/", '_', substr($text, 0, 100));
                $outputGif = "{$targetDir}/{$filename}-{$index}-{$normalizedText}.gif";

                $this->info("Generating GIF for {$start} to {$end}");
                $videoPath = escapeshellarg($video);

                $cleanSubtitleText = html_entity_decode(strip_tags($subtitle['text']));
// Remove problematic characters
                $cleanSubtitleText = str_replace([';', '&', '"', '\''], '', $cleanSubtitleText);
// Also, limit the text length if it's extremely long
                $cleanSubtitleText = substr($cleanSubtitleText, 0, 100);

// If the subtitle is too long, split it into two lines
                if (strlen($cleanSubtitleText) > 40) {
                    $splitPosition = strrpos(substr($cleanSubtitleText, 0, 40), ' ');
                    $line1 = substr($cleanSubtitleText, 0, $splitPosition);
                    $line2 = substr($cleanSubtitleText, $splitPosition + 1);
                    $cleanSubtitleText = "{$line1}\n{$line2}";
                }
                $cleanSubtitleText = escapeshellarg($cleanSubtitleText);
                $outputGif = "{$targetDir}/{$filename}-{$index}-{$normalizedText}.gif";
                $outputGifEscaped = escapeshellarg($outputGif);

                // Adjusted ffmpeg command
                $cmd = "ffmpeg -ss {$start} -to {$end} -i {$videoPath} -vf \"scale=iw*0.25:ih*0.25,drawtext=text={$cleanSubtitleText}:x=(w-text_w)/2:y=h-th-40:fontsize=15:fontcolor=white:borderw=2:bordercolor=black\" -y {$outputGifEscaped}";
                system("ffmpeg -i {$video} 2>&1 | grep 'Duration'");

                shell_exec($cmd);
            }
        }

        $this->info("All videos processed!");
    }

    private function getSubtitleFile($videoFilename, $subtitleFiles) {
        $videoBaseName = pathinfo($videoFilename, PATHINFO_FILENAME);

        // Using levenshtein function to get closest matching filename
        $shortest = -1;
        $closest = '';

        foreach ($subtitleFiles as $subtitleFile) {
            $subtitleBaseName = pathinfo($subtitleFile, PATHINFO_FILENAME);
            $lev = levenshtein($videoBaseName, $subtitleBaseName);

            if ($lev == 0) {
                $closest = $subtitleFile;
                break;
            }

            if ($lev <= $shortest || $shortest < 0) {
                $closest = $subtitleFile;
                $shortest = $lev;
            }
        }

        return $closest;
    }

    private function parseSubtitles($subtitleFile) {
        $contents = file_get_contents($subtitleFile);
        $lines = array_map('trim', explode("\n", $contents));

        $subtitles = [];
        $buffer = [];

        foreach ($lines as $line) {
            if (empty($line) && count($buffer) > 1) {
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
        if (!$time || count(explode(":", $time)) !== 3) {
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
