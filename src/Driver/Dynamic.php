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

use MKCG\Image\QOI\Context;
use MKCG\Image\QOI\Format;

class Dynamic
{
    public static function loadFromFile(string $filepath): ?Context
    {
        $function = match(true) {
            function_exists('vips_image_new_from_file') => static::useVips(...),
            class_exists("\Imagick") => static::useImagick(...),
            class_exists("\GdImage") => static::useGdImage(...),
            default => throw new DriverException()
        };

        return $function($filepath);
    }

    public static function convertInto(Context $reader, string $filepath, Format $format): void
    {
        $function = match(true) {
            class_exists("\Imagick") => Imagick::convertInto(...),
            class_exists("\GdImage") => GdImage::convertInto(...),
            default => throw new DriverException(),
        };

        $function($reader, $filepath, $format);
    }

    private static function useGdImage(string $filepath): ?Context
    {
        try {
            $image = GdImage::loadFromFile($filepath);
            $descriptor = GdImage::createImageDescriptor($image, $filepath);
        } catch (DriverException $e) {
            return null;
        }

        $iterator = GdImage::createIterator($image, $descriptor);

        return new Context($descriptor, $iterator);
    }

    private static function useImagick(string $filepath): ?Context
    {
        try {
            $image = Imagick::loadFromFile($filepath);
            $descriptor = Imagick::createImageDescriptor($image, $filepath);
        } catch (DriverException $e) {
            return null;
        }

        $iterator = Imagick::createIterator($image, $descriptor);

        return new Context($descriptor, $iterator);
    }

    private static function useVips(string $filepath): ?Context
    {
        try {
            $image = Vips::loadFromFile($filepath);
            $descriptor = Vips::createImageDescriptor($image, $filepath);
        } catch (DriverException $e) {
            return null;
        }

        $iterator = Vips::createIterator($image, $descriptor);

        return new Context($descriptor, $iterator);
    }
}
