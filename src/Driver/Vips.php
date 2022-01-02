<?php

/**
 * MIT License
 *
 * Copyright (c) 2021 Kevin Masseix
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace MKCG\Image\QOI\Driver;

use MKCG\Image\QOI\ImageDescriptor;
use MKCG\Image\QOI\Colorspace;
use MKCG\Image\QOI\Context;
use MKCG\Image\QOI\Format;

class Vips
{
    public static function loadFromFile(string $filepath): mixed
    {
        return match(($result = vips_image_new_from_file($filepath, []))) {
            -1 => throw new DriverException(),
            default => $result['out']
        };
    }

    public static function createImageDescriptor($image, string $filepath): ImageDescriptor
    {
        $width    = static::get($image, 'width');
        $height   = static::get($image, 'height');
        $bands    = static::get($image, 'bands');
        $interpretation = static::get($image, 'interpretation');
        $channels = 3;

        if ($bands === 2 || $bands > 4 || ($bands === 4 && $interpretation !== "cmyk")) {
            $channels = 4;
        }

        return new ImageDescriptor($width, $height, $channels, Colorspace::SRGB);
    }

    public static function createIterator($image, ImageDescriptor $descriptor): iterable
    {
        $bands = static::get($image, 'bands');

        // each chunk use no more than 1 MB
        $lines = max(1, (int) floor((1024 * 1024) / $descriptor->width / $bands));

        for ($y = 0; $y < $descriptor->height; $y += $lines) {
            $height = match($y + $lines > $descriptor->height) {
                true => $descriptor->height - $y,
                default => $lines,
            };

            $chunk = match (($result = vips_call('crop', $image, 0, $y, $descriptor->width, $height))) {
                -1 => throw new DriverException(),
                default => $result['out']
            };

            $bin = vips_image_write_to_memory($chunk) or throw new DriverException();
            $length = strlen($bin);

            if ($bands === 1) {
                for ($i = 0; $i < $length; $i++) {
                    $byte = ord($bin[$i]);
                    $alpha = $descriptor->channels === 4 ? $byte : 255;
                    yield [ $byte, $byte, $byte, $alpha ];
                }
            } else {
                for ($i = 0; $i < $length; $i += $descriptor->channels) {
                    $pixel = [
                        ord($bin[$i]),
                        ord($bin[$i + 1]),
                        ord($bin[$i + 2]),
                        $descriptor->channels == 4 ? ord($bin[$i + 3]) : 255,
                    ];

                    yield $pixel;
                }
            }
        }
    }

    private static function get($image, $name)
    {
        return match(($result = vips_image_get($image, $name))) {
            -1 => throw new DriverException(),
            default => $result['out'],
        };
    }
}
