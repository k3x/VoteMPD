dnl $Id: config.m4,v 1.1 2004/06/21 07:57:43 schst Exp $
dnl config.m4 for extension id3

PHP_ARG_ENABLE(id3, whether to enable id3 support,
[  --enable-id3           Enable id3 support])

if test "$PHP_ID3" != "no"; then
  PHP_NEW_EXTENSION(id3, id3.c, $ext_shared)
fi
