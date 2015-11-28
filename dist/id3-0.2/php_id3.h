/*
  +----------------------------------------------------------------------+
  | PHP Version 5                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2004 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.0 of the PHP license,       |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_0.txt.                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Authors: Stephan Schmidt <schst@php.net>                             |
  |          Carsten Lucke <luckec@php.net>                              |
  +----------------------------------------------------------------------+
*/

/* $Id: php_id3.h,v 1.8 2004/09/02 16:35:43 luckec Exp $ */

#ifndef PHP_ID3_H
#define PHP_ID3_H

extern zend_module_entry id3_module_entry;
#define phpext_id3_ptr &id3_module_entry

#ifdef PHP_WIN32
#define PHP_ID3_API __declspec(dllexport)
#else
#define PHP_ID3_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

PHP_MINIT_FUNCTION(id3);
PHP_MSHUTDOWN_FUNCTION(id3);
PHP_MINFO_FUNCTION(id3);

PHP_FUNCTION(id3_get_tag);
PHP_FUNCTION(id3_get_version);
PHP_FUNCTION(id3_set_tag);
PHP_FUNCTION(id3_remove_tag);
PHP_FUNCTION(id3_get_genre_list);
PHP_FUNCTION(id3_get_genre_name);
PHP_FUNCTION(id3_get_genre_id);
PHP_FUNCTION(id3_get_frame_short_name);
PHP_FUNCTION(id3_get_frame_long_name);

/* 
  	Declare any global variables you may need between the BEGIN
	and END macros here:	 

ZEND_BEGIN_MODULE_GLOBALS(id3)
	long  global_value;
	char *global_string;
ZEND_END_MODULE_GLOBALS(id3)
*/

/* In every utility function you add that needs to use variables 
   in php_id3_globals, call TSRMLS_FETCH(); after declaring other 
   variables used by that function, or better yet, pass in TSRMLS_CC
   after the last function argument and declare your utility function
   with TSRMLS_DC after the last declared argument.  Always refer to
   the globals in your function as ID3_G(variable).  You are 
   encouraged to rename these macros something shorter, see
   examples in any other php module directory.
*/

#ifdef ZTS
#define ID3_G(v) TSRMG(id3_globals_id, zend_id3_globals *, v)
#else
#define ID3_G(v) (id3_globals.v)
#endif

#endif	/* PHP_ID3_H */


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
