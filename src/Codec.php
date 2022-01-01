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

namespace MKCG\Image\QOI;

use MKCG\Image\QOI\Writer\Writer;

final class Codec
{
    private const BUFFER_SIZE = 1024;

    private int $position = 0;

    public static function encode(iterable $iterator, ImageDescriptor $descriptor, Writer $writer): void
    {
        $buffer = \FFI::new("unsigned char[" . static::BUFFER_SIZE . "]");
        $position = 0;

        $buffer[$position++] = ord('q');
        $buffer[$position++] = ord('o');
        $buffer[$position++] = ord('i');
        $buffer[$position++] = ord('f');

        $buffer[$position++] = ($descriptor->width >> 24) & 0xFF;
        $buffer[$position++] = ($descriptor->width >> 16) & 0xFF;
        $buffer[$position++] = ($descriptor->width >> 8) & 0xFF;
        $buffer[$position++] = $descriptor->width & 0xFF;

        $buffer[$position++] = ($descriptor->height >> 24) & 0xFF;
        $buffer[$position++] = ($descriptor->height >> 16) & 0xFF;
        $buffer[$position++] = ($descriptor->height >> 8) & 0xFF;
        $buffer[$position++] = $descriptor->height & 0xFF;

        $buffer[$position++] = $descriptor->channels & 0xFF;
        $buffer[$position++] = $descriptor->colorspace->value & 0xFF;

        $indexes = static::initIndexes();
        $length = $descriptor->countPixels();
        $offset = 0;
        $prev = static::initPrevPixel();
        $run  = 0;

        foreach ($iterator as $px) {
            if (static::samePixel($px, $prev)) {
                $run++;

                if ($run >= 62 || $offset >= $length) {
                    $buffer[$position++] = OpCode::RUN->value | ($run - 1);
                    $run = 0;
                }
            } else {
                if ($run > 0) {
                    $buffer[$position++] = OpCode::RUN->value | ($run - 1);
                    $run = 0;
                }

                $indexPos = static::indexPos($px);

                if (static::samePixel($px, $indexes[$indexPos])) {
                    $buffer[$position++] = OpCode::INDEX->value | $indexPos;
                } else {
                    $indexes[$indexPos] = $px;

                    if ($px[3] === $prev[3]) {
                        $vr = $px[0] - $prev[0];
                        $vg = $px[1] - $prev[1];
                        $vb = $px[2] - $prev[2];

                        $vgR = $vr - $vg;
                        $vgB = $vb - $vg;

                        if (static::isDiff($vr, $vg, $vb)) {
                            $buffer[$position++] = OpCode::DIFF->value | (($vr + 2) << 4) | (($vg + 2) << 2) | ($vb + 2);
                        } else if (static::isLuma($vg, $vgR, $vgB)) {
                            $buffer[$position++] = OpCode::LUMA->value | ($vg + 32);
                            $buffer[$position++] = ($vgR + 8) << 4 | ($vgB + 8);
                        } else {
                            $buffer[$position++] = OpCode::RGB->value;
                            $buffer[$position++] = $px[0];
                            $buffer[$position++] = $px[1];
                            $buffer[$position++] = $px[2];
                        }
                    } else {
                        $buffer[$position++] = OpCode::RGBA->value;
                        $buffer[$position++] = $px[0];
                        $buffer[$position++] = $px[1];
                        $buffer[$position++] = $px[2];
                        $buffer[$position++] = $px[3];
                    }
                }
            }

            $prev = $px;
            $offset++;

            if ($position + 20 > static::BUFFER_SIZE) {
                $writer->writeBytes($buffer, $position);
                $position = 0;
            }
        }

        if ($run > 0) {
            $buffer[$position++] = OpCode::RUN->value | ($run - 1);
        }

        $buffer[$position++] = 0x00;
        $buffer[$position++] = 0x00;
        $buffer[$position++] = 0x00;
        $buffer[$position++] = 0x00;
        $buffer[$position++] = 0x00;
        $buffer[$position++] = 0x00;
        $buffer[$position++] = 0x00;
        $buffer[$position++] = 0x01;

        $writer->writeBytes($buffer, $position);
        $position = 0;
    }

    public static function decode(iterable $bytes): Context
    {
        $header = array_fill(0, 14, 0x00);
        $read = 0;

        foreach ($bytes as $i => $byte) {
            $header[$i] = $byte;

            if (++$read == 14) {
                break;
            }
        }

        if ($read !== 14
            || $header[0] != 'q'
            || $header[1] != 'o'
            || $header[2] != 'i'
            || $header[3] != 'f'
        ) {
            throw new \Exception();
        }

        $width = (ord($header[4]) << 24)
            + (ord($header[5]) << 16)
            + (ord($header[6]) << 8)
            + ord($header[7]);

        $height = (ord($header[8]) << 24)
            + (ord($header[9]) << 16)
            + (ord($header[10]) << 8)
            + ord($header[11]);

        $channels = ord($header[12]);
        $colorspace = ord($header[13]);

        $descriptor = new ImageDescriptor(
            $width,
            $height,
            $channels,
            match ($colorspace) {
                Colorspace::SRGB->value => Colorspace::SRGB,
                Colorspace::LINEAR->value => Colorspace::LINEAR,
                default => throw new \Exception(),
            }
        );

        $bytes = match(true) {
            $bytes instanceof \Iterator => (function($bytes) {
                while (true) {
                    $bytes->next();
                    $byte = $bytes->current();

                    if ($byte === null) {
                        break;
                    }

                    yield ord($byte);
                }
            })($bytes),
            default => (function($bytes) {
                $count = count($bytes);

                for ($i = 14; $i < $count; $i++) {
                    yield ord($bytes[$i]);
                }
            })($bytes),
        };

        $iterator = function($bytes, $descriptor) {
            $indexes = static::initIndexes();
            $length = $descriptor->countPixels();
            $offset = 0;
            $prev = static::initPrevPixel();
            $run  = 0;
            $decoded = 0;
            $pixel = $prev;

            while ($decoded < $length) {
                if ($run > 0) {
                    $run--;
                    $decoded++;

                    yield $pixel;
                } else {
                    $byte = $bytes->current();

                    if ($byte === OpCode::RGB->value) {
                        $bytes->next();
                        $pixel[0] = $bytes->current();

                        $bytes->next();
                        $pixel[1] = $bytes->current();

                        $bytes->next();
                        $pixel[2] = $bytes->current();

                        $decoded++;

                        yield $pixel;
                    } else if ($byte === OpCode::RGBA->value) {
                        $bytes->next();
                        $pixel[0] = $bytes->current();

                        $bytes->next();
                        $pixel[1] = $bytes->current();

                        $bytes->next();
                        $pixel[2] = $bytes->current();

                        $bytes->next();
                        $pixel[3] = $bytes->current();

                        $decoded++;

                        yield $pixel;
                    } else if (($byte & 0xc0) === OpCode::INDEX->value) {
                        $pixel = $indexes[$byte];

                        $decoded++;

                        yield $pixel;
                    } else if (($byte & 0xc0) === OpCode::DIFF->value) {
                        $vr = (($byte >> 4) & 0x03) -2;
                        $vg = (($byte >> 2) & 0x03) -2;
                        $vb = ($byte        & 0x03) -2;

                        $pixel[0] += $vr;
                        $pixel[1] += $vg;
                        $pixel[2] += $vb;

                        $decoded++;

                        yield $pixel;
                    } else if (($byte & 0xc0) === OpCode::LUMA->value) {
                        $bytes->next();
                        $second = $bytes->current();

                        $vg = ($byte & 0x3f) - 32;

                        $pixel[0] += $vg - 8 + (($second >> 4) & 0x0f);
                        $pixel[1] += $vg;
                        $pixel[2] += $vg - 8 + ($second        & 0x0f);

                        $decoded++;

                        yield $pixel;
                    } else if (($byte & 0xc0) === OpCode::RUN->value) {
                        $run = ($byte & 0x3f) + 1;
                    }

                    $bytes->next();
                    $prev = $pixel;

                    $indexPos = static::indexPos($pixel);
                    $indexes[$indexPos] = $pixel;
                }
            }
        };

        return new Context($descriptor, $iterator($bytes, $descriptor));
    }

    private static function initPrevPixel(): array
    {
        return [ 0, 0, 0, 255 ];
    }

    private static function initIndexes(): array
    {
        return array_fill(0, 64, [0, 0, 0, 0]);
    }

    private static function indexPos(array $px): int
    {
        $indexPos = $px[0] * 3;
        $indexPos += $px[1] * 5;
        $indexPos += $px[2] * 7;
        $indexPos += $px[3] * 11;
        $indexPos %= 64;

        return $indexPos;
    }

    private static function samePixel(array $px, array $prev): bool
    {
        return $px[0] === $prev[0]
            && $px[1] === $prev[1]
            && $px[2] === $prev[2]
            && $px[3] === $prev[3];
    }

    private static function isDiff(int $vr, int $vg, int $vb): bool
    {
        return $vr > -3 && $vr < 2
            && $vg > -3 && $vg < 2
            && $vb > -3 && $vb < 2;
    }

    private static function isLuma(int $vg, int $vgR, int $vgB): bool
    {
        return $vgR > -9 && $vgR < 8
            && $vg > -33 && $vg < 32
            && $vgB > -9 && $vgB < 8;
    }
}
