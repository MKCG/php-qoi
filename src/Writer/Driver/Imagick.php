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

class Imagick
{
    public static function loadFromFile(string $filepath): ?\Imagick
    {
        return new \Imagick($filepath);
    }

    public static function createImageDescriptor(\Imagick $image, string $filepath): ImageDescriptor
    {
        return new ImageDescriptor(
            $image->getImageWidth(),
            $image->getImageHeight(),
            match ($image->getImageAlphaChannel()) {
                \Imagick::ALPHACHANNEL_UNDEFINED => 3,
                default => 4,
            },
            Colorspace::SRGB,
        );
    }

    public static function createIterator(\Imagick $image, ImageDescriptor $descriptor): iterable
    {
        return static::createPixelIterator($image, $descriptor->channels);
    }

    /**
     * This Iterator use a LOT of memory you should rather use static::createPixelIterator() unless the image is small
     */
    public static function createArrayIterator(\Imagick $image, int $channels): iterable
    {
        $order = match ($channels) {
            3 => "RGB",
            4 => "RGBA",
            default => throw new DriverException(),
        };

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        $pixels = $image->exportImagePixels(0, 0, $width, $height, $order, \Imagick::PIXEL_CHAR);

        for ($i = 0, $count = count($pixels); $i < $count; $i += $channels) {
            yield [
                $pixels[$i],
                $pixels[$i + 1],
                $pixels[$i + 2],
                $channels == 4 ? $pixels[$i + 3] : 255
            ];
        }
    }

    public static function createPixelIterator(\Imagick $image, int $channels): iterable
    {
        $iterator = $image->getPixelIterator();

        foreach ($iterator as $row => $pixels) {
            foreach ($pixels as $column => $pixel) {
                $px = $pixel->getColor(2);
                $px = array_values($px);

                if ($channels == 3) {
                    $px[3] = 255;
                }

                yield $px;
            }

            $iterator->syncIterator();
        }
    }
}
