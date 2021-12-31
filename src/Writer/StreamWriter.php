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

namespace MKCG\Image\QOI\Writer;

use MKCG\Image\QOI\ImageDescriptor;
use MKCG\Image\QOI\OpCode;

class StreamWriter implements Writer
{
    private int $written = 0;

    public function __construct(
        private $stream,
    ) { }

    public function countWritten(): int
    {
        return $this->written;
    }

    public function writeHeader(ImageDescriptor $descriptor): void
    {
        fwrite($this->stream, pack('C', ord('q')), 1);
        fwrite($this->stream, pack('C', ord('o')), 1);
        fwrite($this->stream, pack('C', ord('i')), 1);
        fwrite($this->stream, pack('C', ord('f')), 1);

        fwrite($this->stream, pack('C', ($descriptor->width >> 24) & 0xFF), 1);
        fwrite($this->stream, pack('C', ($descriptor->width >> 16) & 0xFF), 1);
        fwrite($this->stream, pack('C', ($descriptor->width >> 8) & 0xFF), 1);
        fwrite($this->stream, pack('C', $descriptor->width & 0xFF), 1);

        fwrite($this->stream, pack('C', ($descriptor->height >> 24) & 0xFF), 1);
        fwrite($this->stream, pack('C', ($descriptor->height >> 16) & 0xFF), 1);
        fwrite($this->stream, pack('C', ($descriptor->height >> 8) & 0xFF), 1);
        fwrite($this->stream, pack('C', $descriptor->height & 0xFF), 1);

        fwrite($this->stream, pack('C', $descriptor->channels & 0xFF), 1);
        fwrite($this->stream, pack('C', $descriptor->colorspace->value & 0xFF), 1);
        $this->written += 14;
    }

    public function writeTail(): void
    {
        fwrite($this->stream, pack('C', 0x00), 1);
        fwrite($this->stream, pack('C', 0x00), 1);
        fwrite($this->stream, pack('C', 0x00), 1);
        fwrite($this->stream, pack('C', 0x00), 1);
        fwrite($this->stream, pack('C', 0x00), 1);
        fwrite($this->stream, pack('C', 0x00), 1);
        fwrite($this->stream, pack('C', 0x00), 1);
        fwrite($this->stream, pack('C', 0x01), 1);
        $this->written += 8;
    }

    public function writeRun(int $run): void
    {
        fwrite($this->stream, pack('C', OpCode::RUN->value | ($run - 1)), 1);
        $this->written++;
    }

    public function writeDiff(int $vr, int $vg, int $vb): void
    {
        fwrite($this->stream, pack('C', OpCode::DIFF->value | (($vr + 2) << 4) | (($vg + 2) << 2) | ($vb + 2)), 1);
        $this->written++;
    }

    public function writeLuma(int $vg, int $vgR, int $vgB): void
    {        
        fwrite($this->stream, pack('C', OpCode::LUMA->value | ($vg + 32)), 1);
        fwrite($this->stream, pack('C', ($vgR + 8) << 4 | ($vgB + 8)), 1);
        $this->written += 2;
    }

    public function writeIndex(int $index): void
    {
        fwrite($this->stream, pack('C', OpCode::INDEX->value | $index), 1);
        $this->written++;
    }

    public function writeRGB(array $px): void
    {
        fwrite($this->stream, pack('C', OpCode::RGB->value), 1);
        fwrite($this->stream, pack('C', $px[0]), 1);
        fwrite($this->stream, pack('C', $px[1]), 1);
        fwrite($this->stream, pack('C', $px[2]), 1);
        $this->written += 4;
    }

    public function writeRGBA(array $px): void
    {
        fwrite($this->stream, pack('C', OpCode::RGBA->value), 1);
        fwrite($this->stream, pack('C', $px[0]), 1);
        fwrite($this->stream, pack('C', $px[1]), 1);
        fwrite($this->stream, pack('C', $px[2]), 1);
        fwrite($this->stream, pack('C', $px[3]), 1);
        $this->written += 5;
    }
}
