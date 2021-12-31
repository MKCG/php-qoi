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
    private const BUFFER_SIZE = 1024;

    private int $written = 0;
    private int $position = 0;
    private \FFI\CData $buffer;

    public function __construct(
        private $stream,
    ) {
        $this->buffer = \FFI::new("unsigned char[" . static::BUFFER_SIZE . "]");
    }

    public function countWritten(): int
    {
        return $this->written;
    }

    public function writeHeader(ImageDescriptor $descriptor): void
    {
        $this->buffer[$this->position++] = ord('q');
        $this->buffer[$this->position++] = ord('o');
        $this->buffer[$this->position++] = ord('i');
        $this->buffer[$this->position++] = ord('f');

        $this->buffer[$this->position++] = ($descriptor->width >> 24) & 0xFF;
        $this->buffer[$this->position++] = ($descriptor->width >> 16) & 0xFF;
        $this->buffer[$this->position++] = ($descriptor->width >> 8) & 0xFF;
        $this->buffer[$this->position++] = $descriptor->width & 0xFF;

        $this->buffer[$this->position++] = ($descriptor->height >> 24) & 0xFF;
        $this->buffer[$this->position++] = ($descriptor->height >> 16) & 0xFF;
        $this->buffer[$this->position++] = ($descriptor->height >> 8) & 0xFF;
        $this->buffer[$this->position++] = $descriptor->height & 0xFF;

        $this->buffer[$this->position++] = $descriptor->channels & 0xFF;
        $this->buffer[$this->position++] = $descriptor->colorspace->value & 0xFF;
    }

    public function writeTail(): void
    {
        $this->buffer[$this->position++] = 0x00;
        $this->buffer[$this->position++] = 0x00;
        $this->buffer[$this->position++] = 0x00;
        $this->buffer[$this->position++] = 0x00;
        $this->buffer[$this->position++] = 0x00;
        $this->buffer[$this->position++] = 0x00;
        $this->buffer[$this->position++] = 0x00;
        $this->buffer[$this->position++] = 0x01;

        $this->flush(true);
    }

    private function flush(bool $force = false): void
    {
        if ($this->position + 20 < static::BUFFER_SIZE && $force === false) {
            return;
        }

        fwrite($this->stream, \FFI::string($this->buffer, $this->position), $this->position);

        $this->written += $this->position;
        $this->position = 0;
    }

    public function writeRun(int $run): void
    {
        $this->buffer[$this->position++] = OpCode::RUN->value | ($run - 1);
        $this->flush();
    }

    public function writeDiff(int $vr, int $vg, int $vb): void
    {
        $this->buffer[$this->position++] = OpCode::DIFF->value | (($vr + 2) << 4) | (($vg + 2) << 2) | ($vb + 2);
        $this->flush();
    }

    public function writeLuma(int $vg, int $vgR, int $vgB): void
    {
        $this->buffer[$this->position++] = OpCode::LUMA->value | ($vg + 32);
        $this->buffer[$this->position++] = ($vgR + 8) << 4 | ($vgB + 8);
        $this->flush();
    }

    public function writeIndex(int $index): void
    {
        $this->buffer[$this->position++] = OpCode::INDEX->value | $index;
        $this->flush();
    }

    public function writeRGB(array $px): void
    {
        $this->buffer[$this->position++] = OpCode::RGB->value;
        $this->buffer[$this->position++] = $px[0];
        $this->buffer[$this->position++] = $px[1];
        $this->buffer[$this->position++] = $px[2];
        $this->flush();
    }

    public function writeRGBA(array $px): void
    {
        $this->buffer[$this->position++] = OpCode::RGBA->value;
        $this->buffer[$this->position++] = $px[0];
        $this->buffer[$this->position++] = $px[1];
        $this->buffer[$this->position++] = $px[2];
        $this->buffer[$this->position++] = $px[3];
        $this->flush();
    }
}
