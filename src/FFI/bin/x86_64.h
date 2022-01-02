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

#define FFI_SCOPE "PHP_QOI"
#define FFI_LIB "./x86_64.so"

typedef struct {
    unsigned int width;
    unsigned int height;
    unsigned char channels;
    unsigned char colorspace;
} qoi_desc;

typedef union {
    struct { unsigned char r, g, b, a; } rgba;
    unsigned int v;
} qoi_rgba_t;

typedef struct {
    int p;
    int run;
    int px_len;
    int px_end;
    int px_pos;
    qoi_rgba_t index[64];
    qoi_rgba_t px_prev;
} qoi_encoder_t;

void qoi_encode_init(qoi_encoder_t *encoder);
void qoi_encode_header(qoi_encoder_t *encoder, unsigned char *output, qoi_desc desc);
int qoi_encode_chunk(qoi_encoder_t *encoder, const unsigned char *input, int length, unsigned char *output, qoi_desc desc);
int qoi_encode_tail(qoi_encoder_t *encoder, unsigned char *output);
