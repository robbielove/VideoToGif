# Video to GIF Converter with Subtitles

This is a Laravel Zero command script that converts videos in a directory into GIFs. If available, subtitles are added to the GIFs.

## Requirements
- PHP >= 7.3
- `ffmpeg` installed and available in the PATH
- Laravel Zero Framework

## Installation

Clone the repository:
```bash
git clone <repository-url>
cd <repository-directory>
```

Install the dependencies:
```bash
composer install
```

## Usage

You can run the conversion command as:

```bash
php artisan app:convert-video-to-gif1 [inputDir] --outputDir=[output_directory_path]
```

- `inputDir`: This is a required argument. Specify the directory where your videos are located.
- `--outputDir`: This is an optional argument. Specify the directory where you want to save the generated GIFs. By default, GIFs are saved in `./output_gifs` directory.

## Features

- Supports multiple video formats: `mp4, mkv, avi, flv, wmv, mov`.
- Supports multiple subtitle formats: `srt, ass, ssa`.
- Automatically matches video files with subtitle files based on their filenames.
- Generates GIFs with a default duration of 10 seconds if no subtitles are found.

## Troubleshooting

The command provides detailed logs about the conversion process, including parsing subtitles and the progress of GIF creation.

## Contributing

If you would like to contribute to the project or report an issue, please open an issue or submit a pull request.

## License

VideoToGif is an open-source software licensed under the MIT license.
