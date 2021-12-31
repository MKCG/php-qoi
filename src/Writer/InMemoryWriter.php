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

class InMemoryWriter implements Writer
{
    private int $position = 0;

    public function __construct(
        private \FFI\CData $output,
    ) { }

    public function __toString(): string
    {
        return \FFI::string($this->output, $this->countWritten());
    }

    public function countWritten(): int
    {
        return $this->position;
    }

    public function writeHeader(ImageDescriptor $descriptor): void
    {
        $this->output[$this->position++] = ord('q');
        $this->output[$this->position++] = ord('o');
        $this->output[$this->position++] = ord('i');
        $this->output[$this->position++] = ord('f');

        $this->output[$this->position++] = ($descriptor->width >> 24) & 0xFF;
        $this->output[$this->position++] = ($descriptor->width >> 16) & 0xFF;
        $this->output[$this->position++] = ($descriptor->width >> 8) & 0xFF;
        $this->output[$this->position++] = $descriptor->width & 0xFF;

        $this->output[$this->position++] = ($descriptor->height >> 24) & 0xFF;
        $this->output[$this->position++] = ($descriptor->height >> 16) & 0xFF;
        $this->output[$this->position++] = ($descriptor->height >> 8) & 0xFF;
        $this->output[$this->position++] = $descriptor->height & 0xFF;

        $this->output[$this->position++] = $descriptor->channels & 0xFF;
        $this->output[$this->position++] = $descriptor->colorspace->value & 0xFF;
    }

    public function writeTail(): void
    {
        $this->output[$this->position++] = 0x00;
        $this->output[$this->position++] = 0x00;
        $this->output[$this->position++] = 0x00;
        $this->output[$this->position++] = 0x00;
        $this->output[$this->position++] = 0x00;
        $this->output[$this->position++] = 0x00;
        $this->output[$this->position++] = 0x00;
        $this->output[$this->position++] = 0x01;
    }

    public function writeRun(int $run): void
    {
        $this->output[$this->position++] = OpCode::RUN->value | ($run - 1);
    }

    public function writeDiff(int $vr, int $vg, int $vb): void
    {
        $this->output[$this->position++] = OpCode::DIFF->value | (($vr + 2) << 4) | (($vg + 2) << 2) | ($vb + 2);        
    }

    public function writeLuma(int $vg, int $vgR, int $vgB): void
    {        
        $this->output[$this->position++] = OpCode::LUMA->value | ($vg + 32);
        $this->output[$this->position++] = ($vgR + 8) << 4 | ($vgB + 8);
    }

    public function writeIndex(int $index): void
    {
        $this->output[$this->position++] = OpCode::INDEX->value | $index;
    }

    public function writeRGB(array $px): void
    {
        $this->output[$this->position++] = OpCode::RGB->value;
        $this->output[$this->position++] = $px[0];
        $this->output[$this->position++] = $px[1];
        $this->output[$this->position++] = $px[2];
    }

    public function writeRGBA(array $px): void
    {
        $this->output[$this->position++] = OpCode::RGBA->value;
        $this->output[$this->position++] = $px[0];
        $this->output[$this->position++] = $px[1];
        $this->output[$this->position++] = $px[2];
        $this->output[$this->position++] = $px[3];
    }
}
