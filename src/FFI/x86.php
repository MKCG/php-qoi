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

namespace MKCG\Image\QOI\FFI;

use MKCG\Image\QOI\Codec;
use MKCG\Image\QOI\Colorspace;
use MKCG\Image\QOI\Context;
use MKCG\Image\QOI\ImageDescriptor;
use MKCG\Image\QOI\Writer\Writer;

class x86 extends Codec
{
    private static $library = null;

    public static function init()
    {
        if (static::$library !== null) {
            return;
        }

        $header = str_replace("/", DIRECTORY_SEPARATOR, __DIR__ . "/bin/x86_64.h");
        $binary = str_replace("/", DIRECTORY_SEPARATOR, __DIR__ . "/bin/x86_64.so");
        static::$library = \FFI::cdef(file_get_contents($header), $binary);
    }

    public static function encode(iterable $iterator, ImageDescriptor $descriptor, Writer $writer): void
    {
        static::init();
        $library = static::$library;

        $inputMaxSize = $descriptor->channels * 65536;
        $outputMaxSize = (($descriptor->channels + 1) * 65536) + 8;

        $input   = \FFI::new("unsigned char[${inputMaxSize}]");
        $output  = \FFI::new("unsigned char[${outputMaxSize}]");
        $encoder = $library->new("qoi_encoder_t");
        $desc    = $library->new("qoi_desc");

        $desc->width      = $descriptor->width;
        $desc->height     = $descriptor->height;
        $desc->channels   = $descriptor->channels;
        $desc->colorspace = $descriptor->colorspace->value;

        $ptrEncoder = \FFI::addr($encoder);

        $library->qoi_encode_init($ptrEncoder);
        $library->qoi_encode_header($ptrEncoder, $output, $desc);
        $writer->writeBytes($output, 14);

        $inputPos = 0;

        foreach ($iterator as $px) {
            $input[$inputPos++] = $px[0];
            $input[$inputPos++] = $px[1];
            $input[$inputPos++] = $px[2];

            if ($descriptor->channels === 4) {
                $input[$inputPos++] = $px[3];
            }

            if ($inputPos >= $inputMaxSize - 10) {
                $written = $library->qoi_encode_chunk($ptrEncoder, $input, $inputPos, $output, $desc);
                $writer->writeBytes($output, $written);
                $inputPos = 0;
            }
        }

        if ($inputPos != 0) {
            $written = $library->qoi_encode_chunk($ptrEncoder, $input, $inputPos, $output, $desc);
            $writer->writeBytes($output, $written);
        }

        $written = $library->qoi_encode_tail($ptrEncoder, $output);
        $writer->writeBytes($output, $written);
    }
}
