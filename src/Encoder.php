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

class Encoder
{
    public static function encode(iterable $iterator, ImageDescriptor $descriptor, Writer $writer): void
    {
        $indexes = Codec::initIndexes();
        $length = $descriptor->countBytes();
        $offset = 0;
        $prev = Codec::initPrevPixel();
        $run  = 0;

        $writer->writeHeader($descriptor);

        foreach ($iterator as $px) {
            if (Codec::samePixel($px, $prev)) {
                $run++;

                if ($run >= 62 || $offset >= $length) {
                    $writer->writeRun($run);
                    $run = 0;
                }
            } else {
                if ($run > 0) {
                    $writer->writeRun($run);
                    $run = 0;
                }

                $indexPos = Codec::indexPos($px);

                if (Codec::samePixel($px, $indexes[$indexPos])) {
                    $writer->writeIndex($indexPos);
                } else {
                    $indexes[$indexPos] = $px;

                    if ($px[3] === $prev[3]) {
                        $vr = $px[0] - $prev[0];
                        $vg = $px[1] - $prev[1];
                        $vb = $px[2] - $prev[2];

                        $vgR = $vr - $vg;
                        $vgB = $vb - $vg;

                        if (Codec::isDiff($vr, $vg, $vb)) {
                            $writer->writeDiff($vr, $vg, $vb);
                        } else if (Codec::isLuma($vg, $vgR, $vgB)) {
                            $writer->writeLuma($vg, $vgR, $vgB);
                        } else {
                            $writer->writeRGB($px);
                        }
                    } else {
                        $writer->writeRGBA($px);
                    }
                }
            }

            $prev = $px;
            $offset += $descriptor->channels;
        }

        $writer->writeTail();
    }
}
