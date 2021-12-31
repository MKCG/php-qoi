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

namespace MKCG\Image\QOI\Writer\Driver;

use MKCG\Image\QOI\ImageDescriptor;
use MKCG\Image\QOI\Colorspace;

class GdImage
{
    public static function loadFromFile(string $filepath): ?\GdImage
    {
        return match (mime_content_type($filepath)) {
            "image/jpeg" => imagecreatefromjpeg($filepath),
            "image/png"  => imagecreatefrompng($filepath),
            "image/webp" => imagecreatefromwebp($filepath),
            default => throw new DriverException(mime_content_type($filepath)),
        };
    }

    public static function createImageDescriptor(\GdImage $image, string $filepath): ImageDescriptor
    {
        $channels = 3;

        if (imageistruecolor($image)) {
            switch (mime_content_type($filepath)) {
                case "image/jpeg":
                    break;

                case "image/png":
                    $marker = file_get_contents($filepath, false, null, 25, 1);
                    $channels = match(ord($marker)) {
                        2, 3 => 3,
                        6 => 4,
                        default => throw new DriverException(),
                    };

                    break;

                case "image/webp":
                default: throw new DriverException();
            }
        }

        return new ImageDescriptor(
            imagesx($image),
            imagesy($image),
            $channels,
            Colorspace::SRGB,
        );
    }

    public static function createIterator(\GdImage $image, ImageDescriptor $descriptor): iterable
    {
        for ($y = 0; $y < $descriptor->height; $y++) {
            for ($x = 0; $x < $descriptor->width; $x++) {
                $color = imagecolorat($image, $x, $y);

                // @see: https://github.com/php/php-src/blob/eb6c9eb936c62a5784874079747290672bd2faa2/ext/gd/libgd/gd.h#L85
                $px = [
                    ($color & 0xFF0000) >> 16,
                    ($color & 0x00FF00) >> 8,
                    ($color & 0x0000FF),
                    255
                ];

                if ($descriptor->channels == 4) {
                    $alpha = ($color & 0x7F000000) >> 24;

                    // @see: https://github.com/php/php-src/blob/2f85d79165ad5744cc411194c159f1ce43e1ec0a/ext/gd/libgd/gd_png.c#L736
                    $px[3] = $alpha = 255 - (($alpha << 1) + ($alpha >> 6));
                }

                yield $px;
            }
        }
    }
}
