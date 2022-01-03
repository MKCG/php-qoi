build:
	gcc qoi.c -O1 -std=gnu99 -shared -o "./../bin/$(shell uname -p).so"

build_arm_32b:
	arm-linux-gnueabi-gcc qoi.c -O1 -std=gnu99 -shared -o "./../bin/arm_32b.so"

build_arm_64b:
	aarch64-linux-gnu-gcc qoi.c -O1 -std=gnu99 -shared -o "./../bin/arm_64b.so"
