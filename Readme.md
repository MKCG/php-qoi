# QOI encoder / decoder

[QOI Image]((https://github.com/phoboslab/qoi)) encoder and decoder written in pure PHP

## Performance

The `StreamWriter` can encode at about 1MiB/s
The `InMemoryWriter` can encode at about 5MiB/s

## Usage

Convert a PNG file using a stream :
---

```php
use MKCG\Image\QOI\Encoder;
use MKCG\Image\QOI\Writer\StreamWriter;
use MKCG\Image\QOI\Writer\Driver\Dynamic;

$inputFilepath  = "/foobar.png";
$outputFilepath = "/foobar.qoi";

$context = Dynamic::loadFromFile($inputFilepath);

if ($context) {
    $outputFile = fopen($outputFilepath, 'w');

    if ($outputFile) {
        $writer = new StreamWriter($outputFile);
        Encoder::encode($context->iterator, $context->descriptor, $writer);
        fclose($outputFile);
    }
}
```

Convert a PNG file in-memory :
---

```php
use MKCG\Image\QOI\Encoder;
use MKCG\Image\QOI\Writer\InMemoryWriterFactory;
use MKCG\Image\QOI\Writer\Driver\Dynamic;

$inputFilepath  = "/foobar.png";
$outputFilepath = "/foobar.qoi";

$context = Dynamic::loadFromFile($inputFilepath);

if ($context) {
    $outputFile = fopen($outputFilepath, 'w');

    if ($outputFile) {
        $writer = (new Writer\InMemoryWriterFactory)->createWriter($context->descriptor);
        Encoder::encode($context->iterator, $context->descriptor, $writer);
        fwrite($outputFile, (string) $writer, $writer->countWritten());
        fclose($outputFile);
    }
}
```

Create an image from scratch :
---

```php
use MKCG\Image\QOI\Encoder;
use MKCG\Image\QOI\Colorspace;
use MKCG\Image\QOI\ImageDescriptor;
use MKCG\Image\QOI\Writer\StreamWriter;
use MKCG\Image\QOI\Writer\WriterContext;

$outputFilepath = "/white.qoi";

$width    = 800;
$height   = 600;
$channels = 3;

$context = new WriterContext(
    new ImageDescriptor($width, $height, $channels, Colorspace::SRGB),
    (function() use ($width, $height, $channels) {
        $size = $width * $height * $channels;
        for ($i = 0; $i < $size; $i++) {
            yield [ 255, 255, 255, 255 ];
        }
    })(),
);

$outputFile = fopen($outputFilepath, 'w');

if ($outputFile) {
    $writer = new StreamWriter($outputFile);
    Encoder::encode($context->iterator, $context->descriptor, $writer);
    fclose($outputFile);
}

```

## Drivers

| Name    | Requirements                  | Description                                                  |
| ------- | ----------------------------- | ------------------------------------------------------------ |
| Dynamic | one of : ext-imagick, ext-gd  | Load the image using the appropriate PHP image extension     |
| Gd      | ext-gd                        |                                                              |
| Imagick | ext-imagick                   | imagick must have been compiled against ImageMagick >= 6.4.0 |


## Supported images format

Driver GdImage : png and jpeg only

Driver Imagick : any image format that can be loaded by \Imagick
