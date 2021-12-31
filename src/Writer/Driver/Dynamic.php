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

use MKCG\Image\QOI\Writer\WriterContext;

class Dynamic
{
    public static function loadFromFile(string $filepath): ?WriterContext
    {
        $fallbacks = [
            static::useImagick(...),
            static::useGdImage(...)
        ];

        foreach ($fallbacks as $fallback) {
            $output = $fallback($filepath);

            if ($output !== null) {
                return $output;
            }
        }

        return null;
    }

    private static function useGdImage(string $filepath): ?WriterContext
    {
        if (!class_exists("\GdImage")) {
            return null;
        }

        try {
            $image = GdImage::loadFromFile($filepath);
            $descriptor = GdImage::createImageDescriptor($image, $filepath);
        } catch (\Exception $e) {
            return null;
        }

        $iterator = GdImage::createIterator($image, $descriptor);

        return new WriterContext($descriptor, $iterator);
    }

    private static function useImagick(string $filepath): ?WriterContext
    {
        if (!class_exists("\Imagick")) {
            return null;
        }

        try {
            $image = Imagick::loadFromFile($filepath);
            $descriptor = Imagick::createImageDescriptor($image, $filepath);
        } catch (\Exception $e) {
            return null;
        }

        $iterator = Imagick::createIterator($image, $descriptor);

        return new WriterContext($descriptor, $iterator);
    }
}
