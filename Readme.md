# QOI encoder / decoder

[QOI Image]((https://github.com/phoboslab/qoi)) encoder and decoder written in pure PHP.


It can encode and decode a few megabytes per second with a small memory footprint.


On x86_64 architectures, it will automatically use the [FFI extension](https://www.php.net/manual/en/book.ffi.php) to encode the file using the provided C library.


## Usage

Convert a PNG file using a stream :
---

```php
use MKCG\Image\QOI\Codec;
use MKCG\Image\QOI\Driver\Dynamic;
use MKCG\Image\QOI\Writer\StreamWriter;

$inputFilepath  = "/foobar.png";
$outputFilepath = "/foobar.qoi";

$context = Dynamic::loadFromFile($inputFilepath);

if ($context) {
    $outputFile = fopen($outputFilepath, 'w');

    if ($outputFile) {
        $writer = new StreamWriter($outputFile);
        Codec::encode($context->iterator, $context->descriptor, $writer);
        fclose($outputFile);
    }
}
```

Convert a PNG file in-memory :
---

```php
use MKCG\Image\QOI\Codec;
use MKCG\Image\QOI\Driver\Dynamic;
use MKCG\Image\QOI\Writer\InMemoryWriterFactory;

$inputFilepath  = "/foobar.png";
$outputFilepath = "/foobar.qoi";

$context = Dynamic::loadFromFile($inputFilepath);

if ($context) {
    $outputFile = fopen($outputFilepath, 'w');

    if ($outputFile) {
        $writer = (new Writer\InMemoryWriterFactory)->createWriter($context->descriptor);
        Codec::encode($context->iterator, $context->descriptor, $writer);
        fwrite($outputFile, (string) $writer, $writer->countWritten());
        fclose($outputFile);
    }
}
```

Create an image from scratch :
---

```php
use MKCG\Image\QOI\Codec;
use MKCG\Image\QOI\Colorspace;
use MKCG\Image\QOI\Context;
use MKCG\Image\QOI\ImageDescriptor;
use MKCG\Image\QOI\Writer\StreamWriter;

$outputFilepath = "/white.qoi";

$width    = 800;
$height   = 600;
$channels = 3;

$context = new Context(
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
    Codec::encode($context->iterator, $context->descriptor, $writer);
    fclose($outputFile);
}

```

### Decode a QOI image

```php
use MKCG\Image\QOI\Codec;

function createFileIterator($filepath): \Generator
{
    $bytes = file_get_contents($filepath);

    for ($i = 0; $i < strlen($bytes); $i++) {
        yield $bytes[$i];
    }
};

$filepath = "/input.qoi";

$reader = Codec::decode(createFileIterator($filepath));

$width    = $reader->descriptor->width;
$height   = $reader->descriptor->height;
$channels = $reader->descriptor->channels;

$pixels = iterator_to_array($reader->iterator);
```

### Convert a QOI image

```php
use MKCG\Image\QOI\Codec;
use MKCG\Image\QOI\Format;
use MKCG\Image\QOI\Driver\Dynamic;

function createFileIterator($filepath): \Generator
{
    $handler = fopen($filepath, 'r');

    while (($bytes = fread($handler, 8192)) !== false) {
        for ($i = 0; $i < strlen($bytes); $i++) {
            yield $bytes[$i];
        }
    }

    fclose($handler);
};

$filepath = "/input.qoi";

$reader = Codec::decode(createFileIterator($filepath));
Dynamic::convertInto($reader, "/output.png", Format::PNG);

// Important: you need to create another reader
$reader = Codec::decode(createFileIterator($filepath));
Dynamic::convertInto($reader, "/output.jpg", Format::JPG);
```

## Drivers

| Name    | Requirements                  | Description                                                  |
| ------- | ----------------------------- | ------------------------------------------------------------ |
| Dynamic | one of : ext-imagick, ext-gd  | Manipulates images using the appropriate PHP image extension |
| Gd      | ext-gd                        | gd use only 7 bits for the alpha channel instead of 8        |
| Imagick | ext-imagick                   | imagick must have been compiled against ImageMagick >= 6.4.0 |


## Supported images format

Driver GdImage : png and jpeg only

Driver Imagick : any image format that can be loaded by \Imagick
