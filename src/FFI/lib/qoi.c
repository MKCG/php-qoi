/*

This is an adaptation of the Quite OK Image encoder intended to be used as a PHP FFI

@see: https://github.com/phoboslab/qoi

---

QOI - The "Quite OK Image" format for fast, lossless image compression

Dominic Szablewski - https://phoboslab.org

-- LICENSE: The MIT License(MIT)

Copyright(c) 2021 Dominic Szablewski

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files(the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and / or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions :
The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

#include "qoi.h"

#define QOI_OP_INDEX  0x00 /* 00xxxxxx */
#define QOI_OP_DIFF   0x40 /* 01xxxxxx */
#define QOI_OP_LUMA   0x80 /* 10xxxxxx */
#define QOI_OP_RUN    0xc0 /* 11xxxxxx */
#define QOI_OP_RGB    0xfe /* 11111110 */
#define QOI_OP_RGBA   0xff /* 11111111 */

#define QOI_MASK_2    0xc0 /* 11000000 */

#define QOI_COLOR_HASH(C) (C.rgba.r*3 + C.rgba.g*5 + C.rgba.b*7 + C.rgba.a*11)
#define QOI_MAGIC \
    (((unsigned int)'q') << 24 | ((unsigned int)'o') << 16 | \
     ((unsigned int)'i') <<  8 | ((unsigned int)'f'))
#define QOI_HEADER_SIZE 14

#define QOI_PIXELS_MAX ((unsigned int)400000000)

#define QOI_IS_OP_DIFF(vr, vg, vb) (vr > -3 && vr < 2 && vg > -3 && vg < 2 && vb > -3 && vb < 2)
#define QOI_IS_OP_LUMA(vg, vg_r, vg_b) (vg_r >  -9 && vg_r <  8 && vg   > -33 && vg   < 32 &&  vg_b >  -9 && vg_b <  8)

#define QOI_WRITE_OP_DIFF(output, outputPos, vr, vg, vb) \
    output[outputPos++] = QOI_OP_DIFF | (vr + 2) << 4 | (vg + 2) << 2 | (vb + 2);

#define QOI_WRITE_OP_LUMA(output, outputPos, vg, vg_r, vg_b) \
    output[outputPos++] = QOI_OP_LUMA     | (vg   + 32);     \
    output[outputPos++] = (vg_r + 8) << 4 | (vg_b +  8);

#define QOI_WRITE_OP_RGB(output, outputPos, px)  \
    output[outputPos++] = QOI_OP_RGB;            \
    output[outputPos++] = px.rgba.r;             \
    output[outputPos++] = px.rgba.g;             \
    output[outputPos++] = px.rgba.b;

#define QOI_WRITE_OP_RGBA(output, outputPos, px) \
    output[outputPos++] = QOI_OP_RGBA;           \
    output[outputPos++] = px.rgba.r;             \
    output[outputPos++] = px.rgba.g;             \
    output[outputPos++] = px.rgba.b;             \
    output[outputPos++] = px.rgba.a;


void qoi_encode_init(qoi_encoder_t *encoder) {
    encoder->p = 0;
    encoder->run = 0;
    encoder->px_len = 0;
    encoder->px_end = 0;
    encoder->px_pos = 0;
    encoder->px_prev.rgba.r = 0;
    encoder->px_prev.rgba.g = 0;
    encoder->px_prev.rgba.b = 0;
    encoder->px_prev.rgba.a = 255;

    for (int i = 0; i < 64; i++) {
        encoder->index[i].rgba.r = 0;
        encoder->index[i].rgba.g = 0;
        encoder->index[i].rgba.b = 0;
        encoder->index[i].rgba.a = 0;
    }
}

static void qoi_write_32(unsigned char *bytes, int *p, unsigned int v) {
    bytes[(*p)++] = (0xff000000 & v) >> 24;
    bytes[(*p)++] = (0x00ff0000 & v) >> 16;
    bytes[(*p)++] = (0x0000ff00 & v) >> 8;
    bytes[(*p)++] = (0x000000ff & v);
}

void qoi_encode_header(qoi_encoder_t *encoder, unsigned char *output, qoi_desc desc) {
    qoi_write_32(output, &encoder->p, QOI_MAGIC);
    qoi_write_32(output, &encoder->p, desc.width);
    qoi_write_32(output, &encoder->p, desc.height);
    output[encoder->p++] = desc.channels;
    output[encoder->p++] = desc.colorspace;
}

int qoi_encode_chunk(qoi_encoder_t *encoder, const unsigned char *input, int length, unsigned char *output, qoi_desc desc) {
    qoi_rgba_t px;

    int inputPos = 0;
    int outputPos = 0;

    while (inputPos < length) {
        if (desc.channels == 4) {
            px = *(qoi_rgba_t *)(input + inputPos);
        }
        else {
            px.rgba.r = input[inputPos + 0];
            px.rgba.g = input[inputPos + 1];
            px.rgba.b = input[inputPos + 2];
        }

        if (px.v == encoder->px_prev.v) {
            encoder->run++;

            if (encoder->run == 62) {
                output[outputPos++] = QOI_OP_RUN | (encoder->run - 1);
                encoder->run = 0;
            }
        }
        else {
            int index_pos;

            if (encoder->run > 0) {
                output[outputPos++] = QOI_OP_RUN | (encoder->run - 1);
                encoder->run = 0;
            }

            index_pos = QOI_COLOR_HASH(px) % 64;

            if (encoder->index[index_pos].v == px.v) {
                output[outputPos++] = QOI_OP_INDEX | index_pos;
            }
            else {
                encoder->index[index_pos] = px;

                if (px.rgba.a == encoder->px_prev.rgba.a) {
                    signed char vr = px.rgba.r - encoder->px_prev.rgba.r;
                    signed char vg = px.rgba.g - encoder->px_prev.rgba.g;
                    signed char vb = px.rgba.b - encoder->px_prev.rgba.b;

                    signed char vg_r = vr - vg;
                    signed char vg_b = vb - vg;

                    if (QOI_IS_OP_DIFF(vr, vg, vb)) {
                            QOI_WRITE_OP_DIFF(output, outputPos, vr, vg, vb);
                        }
                    else if (QOI_IS_OP_LUMA(vg, vg_r, vg_b)) {
                        QOI_WRITE_OP_LUMA(output, outputPos, vg, vg_r, vg_b);
                    }
                    else {
                        QOI_WRITE_OP_RGB(output, outputPos, px);
                    }
                }
                else {
                    QOI_WRITE_OP_RGBA(output, outputPos, px);
                }
            }
        }

        inputPos += desc.channels;
        encoder->px_prev = px;
    }

    return outputPos;
}

int qoi_encode_tail(qoi_encoder_t *encoder, unsigned char *output) {
    int outputPos = 0;

    if (encoder->run > 0) {
        output[outputPos++] = QOI_OP_RUN | (encoder->run - 1);
        encoder->run = 0;
    }

    output[outputPos++] = 0;
    output[outputPos++] = 0;
    output[outputPos++] = 0;
    output[outputPos++] = 0;
    output[outputPos++] = 0;
    output[outputPos++] = 0;
    output[outputPos++] = 0;
    output[outputPos++] = 1;

    return outputPos;
}
