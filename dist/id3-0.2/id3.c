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
  |          Carsten Lucke <luckec@tool-garage.de>                       |
  +----------------------------------------------------------------------+
*/

/* $Id: id3.c,v 1.27 2004/09/24 17:40:28 luckec Exp $ */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_id3.h"
#include "ext/standard/flock_compat.h"

/* macros */
#define BIT0(a)	(a & 1)
#define BIT1(a)	(a & 2)
#define BIT2(a)	(a & 4)
#define BIT3(a)	(a & 8)
#define BIT4(a)	(a & 16)
#define BIT5(a)	(a & 32)
#define BIT6(a)	(a & 64)
#define BIT7(a)	(a & 128)

const long ID3V2_FRAMEMAP_ENTRIES = 139;

/* constants */
const int ID3V2_BASEHEADER_LENGTH = 10;
const int ID3V2_IDENTIFIER_LENGTH = 3;

/* version constants */
const int ID3_BEST	= 0;
const int ID3_V1_0	= 1;
const int ID3_V1_1	= 3;
const int ID3_V2_1	= 4;
const int ID3_V2_2	= 12;
const int ID3_V2_3	= 28;
const int ID3_V2_4	= 60;

/* id3v2x header flags 
 *
 * The structure of those flags differs in different versions,
 * see struct id3v2HdrFlags _php_id3v2_get_hdrFlags(php_stream *stream TSRMLS_DC)
 * for more details
 * 
 * 1 = flag is set
 * 0 = flag isn't set
 * -1 = id3-version doesn't know about this flag
 */
typedef struct {
	short unsynch;
	short extHdr;
	short experimental;
	short footer;
	short compression;
} id3v2HdrFlags;

typedef struct {
	char id[4];
	short version;
	short revision;
	id3v2HdrFlags flags;
	int size;
	/* actual size of the whole tag */
	int effSize;
} id3v2Header;

typedef struct {
	int tagSize;
	int textEncoding;
	int textFieldSize;
	int imageEncoding;
	int imageSize;
} id3v2ExtHdrFlags_TagRestrictions;

typedef struct {
	short update;
	short crcPresent;
	int crcData;
	short restrictions;
	id3v2ExtHdrFlags_TagRestrictions restrictionData;
} id3v2ExtHdrFlags;

typedef struct {
	int	size;
	int	numFlagBytes;
	id3v2ExtHdrFlags flags;
} id3v2ExtHeader;

typedef struct {
	short tagAlterPreservation;
	short fileAlterPreservation;
	short readOnly;
	short groupingIdentity;
	short groupId; /* Byte of data that follows the flags, if groupingIdentity-flag is set */
	short compression; /* if set, dataLengthIndicator must be set, too */
	short encryption;
	short encryptMethod; /* Byte of data that follows the flags, if encryption-flag is set */
	short unsynch;
	short dataLengthIndicator;
	int dataLength;
} id3v2FrameHeaderFlags;

typedef struct {
	char id[5];
	int size;
	id3v2FrameHeaderFlags flags;
} id3v2FrameHeader;

typedef struct {
	/* id3v2 frame-id */
	char *id;
	/* array-key used for the frame (short name) */
	char *key;
	/* description for the frame (long name)  */
	char *descr;
} id3v2FrameMap;

/* function prototypes */
id3v2Header _php_id3v2_get_header(php_stream *stream TSRMLS_DC);
id3v2ExtHeader _php_id3v2_get_extHeader(php_stream *stream TSRMLS_DC);
id3v2FrameHeader _php_id3v2_get_frameHeader(unsigned char *data, int offset, short version TSRMLS_DC);
int _php_bigEndian_to_Int(char* byteword, int bytewordlen, short synchsafe TSRMLS_DC);
void _php_id3v1_get_tag(php_stream *stream , zval* return_value, int version TSRMLS_DC);
void _php_id3v2_get_tag(php_stream *stream , zval* return_value, int version TSRMLS_DC);
static int _php_id3_get_version(php_stream *stream TSRMLS_DC);
static int _php_id3_write_padded(php_stream *stream, zval **data, int length TSRMLS_DC);
int _php_id3v2_get_framesOffset(php_stream *stream TSRMLS_DC);
int _php_id3v2_get_framesLength(php_stream* stream TSRMLS_DC);
short _php_id3v2_get_frameHeaderLength(short majorVersion TSRMLS_DC);
int _php_deUnSynchronize(unsigned char* buf, int bufLen TSRMLS_DC);
int _php_strnoffcpy(unsigned char *dest, unsigned char *src, int offset, int len TSRMLS_DC);
short _php_id3v2_parseFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC);
short _php_id3v2_parseUFIDFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC);
short _php_id3v2_parseTextFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC);
short _php_id3v2_parseLinkFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC);
void _php_id3v2_buildFrameMap(id3v2FrameMap *map TSRMLS_DC);
void _php_id3v2_addFrameMap(id3v2FrameMap *stack, long offset, char *frameId, char* arrayKey, char *descr TSRMLS_DC);
char *_php_id3v2_getKeyForFrame(id3v2FrameMap *stack, char *frameId TSRMLS_DC);

/* predefined genres */
const int ID3_GENRE_COUNT = 148;
char *id3_genres[148] = { "Blues", "Classic Rock", "Country", "Dance", "Disco", "Funk", "Grunge", "Hip-Hop", "Jazz", "Metal", "New Age",
		"Oldies", "Other", "Pop", "R&B", "Rap", "Reggae", "Rock", "Techno", "Industrial", "Alternative", "Ska", "Death Metal", "Pranks", 
		"Soundtrack", "Euro-Techno", "Ambient", "Trip-Hop", "Vocal", "Jazz+Funk", "Fusion", "Trance", "Classical", "Instrumental", "Acid", 
		"House", "Game", "Sound Clip", "Gospel", "Noise", "Alternative Rock", "Bass", "Soul", "Punk", "Space", "Meditative", "Instrumental Pop", 
		"Instrumental Rock", "Ethnic", "Gothic", "Darkwave", "Techno-Industrial", "Electronic", "Pop-Folk", "Eurodance", "Dream", "Southern Rock", 
		"Comedy", "Cult", "Gangsta", "Top 40", "Christian Rap", "Pop/Funk", "Jungle", "Native US", "Cabaret", "New Wave", "Psychedelic", "Rave", 
		"Showtunes", "Trailer", "Lo-Fi", "Tribal", "Acid Punk", "Acid Jazz", "Polka", "Retro", "Musical", "Rock & Roll", "Hard Rock", "Folk", 
		"Folk-Rock", "National Folk", "Swing", "Fast Fusion", "Bebob", "Latin", "Revival", "Celtic", "Bluegrass", "Avantgarde", "Gothic Rock", 
		"Progressive Rock", "Psychedelic Rock", "Symphonic Rock", "Slow Rock", "Big Band", "Chorus", "Easy Listening", "Acoustic", "Humour", 
		"Speech", "Chanson", "Opera", "Chamber Music", "Sonata", "Symphony", "Booty Bass", "Primus", "Porn Groove", "Satire", "Slow Jam", "Club", 
		"Tango", "Samba", "Folklore", "Ballad", "Power Ballad", "Rhytmic Soul", "Freestyle", "Duet", "Punk Rock", "Drum Solo", "Acapella", 
		"Euro-House", "Dance Hall", "Goa", "Drum & Bass", "Club-House", "Hardcore", "Terror", "Indie", "BritPop", "Negerpunk", "Polsk Punk", 
		"Beat", "Christian Gangsta", "Heavy Metal", "Black Metal", "Crossover", "Contemporary C", "Christian Rock", "Merengue", "Salsa", 
		"Thrash Metal", "Anime", "JPop", "SynthPop" };
		
/* fseek positions */
const int ID3_SEEK_V1_TAG = -128;
const int ID3_SEEK_V1_TITLE = -125;
const int ID3_SEEK_V1_ARTIST = -95;
const int ID3_SEEK_V1_ALBUM = -65;
const int ID3_SEEK_V1_YEAR = -35;
const int ID3_SEEK_V1_COMMENT = -31;
const int ID3_SEEK_V1_TRACK = -2;
const int ID3_SEEK_V1_GENRE = -1;




/* If you declare any globals in php_id3.h uncomment this:
ZEND_DECLARE_MODULE_GLOBALS(id3)
*/

/* {{{ id3_functions[]
 *
 * Every user visible function must have an entry in id3_functions[].
 */
zend_function_entry id3_functions[] = {
	PHP_FE(id3_get_version, NULL)
	PHP_FE(id3_get_tag,	NULL)
	PHP_FE(id3_set_tag, NULL)
	PHP_FE(id3_get_genre_list, NULL)
	PHP_FE(id3_get_genre_name, NULL)
	PHP_FE(id3_get_genre_id, NULL)
	PHP_FE(id3_get_frame_short_name, NULL)
	PHP_FE(id3_get_frame_long_name, NULL)
	PHP_FE(id3_remove_tag, NULL)
	{NULL, NULL, NULL}
};
/* }}} */

/* {{{ id3_module_entry
 */
zend_module_entry id3_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
	STANDARD_MODULE_HEADER,
#endif
	"id3",
	id3_functions,
	PHP_MINIT(id3),
	PHP_MSHUTDOWN(id3),
	NULL,
	NULL,
	PHP_MINFO(id3),
#if ZEND_MODULE_API_NO >= 20010901
	"0.1", /* Replace with version number for your extension */
#endif
	STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_ID3
ZEND_GET_MODULE(id3)
#endif

/* {{{ PHP_MINIT_FUNCTION
 */
PHP_MINIT_FUNCTION(id3)
{
	REGISTER_LONG_CONSTANT("ID3_BEST", ID3_BEST, CONST_CS|CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("ID3_V1_0", ID3_V1_0, CONST_CS|CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("ID3_V1_1", ID3_V1_1, CONST_CS|CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("ID3_V2_1", ID3_V2_1, CONST_CS|CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("ID3_V2_2", ID3_V2_2, CONST_CS|CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("ID3_V2_3", ID3_V2_3, CONST_CS|CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("ID3_V2_4", ID3_V2_4, CONST_CS|CONST_PERSISTENT);
	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(id3)
{
	return SUCCESS;
}

/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(id3)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "id3 support", "enabled");
	php_info_print_table_row(2, "Supported versions", "v1.0, v1.1, v2.2+ (partly)");
	php_info_print_table_end();
}
/* }}} */

/* {{{ proto array id3_get_tag(string file)
   Returns an array containg all information from the id3 tag */
PHP_FUNCTION(id3_get_tag)
{
	zval *arg;
	php_stream *stream;
	
	int	version = ID3_BEST,
		versionCheck = 0,
		opened = 0;
	
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z|l", &arg, &version) == FAILURE) {
		return;
	}

	if (!(version == ID3_BEST || version == ID3_V1_0 || version == ID3_V1_1 || version == ID3_V2_2 || version == ID3_V2_3 || version == ID3_V2_4)) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_get_tag(): Unsupported version given");
		return;
	}

	switch(Z_TYPE_P(arg)) {
		case IS_STRING:
			stream = php_stream_open_wrapper(Z_STRVAL_P(arg), "rb", REPORT_ERRORS|ENFORCE_SAFE_MODE|STREAM_MUST_SEEK, NULL);
			opened = 1;
			break;
		case IS_RESOURCE:
			php_stream_from_zval(stream, &arg)
			break;
		default:
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_get_tag() expects parameter 1 to be string or resource");
			return;
	}

	/* invalid file */
	if(!stream) {
		RETURN_FALSE;
	}
	
	/* check if a tag exists at all */
	versionCheck = _php_id3_get_version(stream TSRMLS_CC);
	if (versionCheck == 0 || versionCheck == ID3_V2_1) {
		/* stream contains no tag or only an unsupported v2.1 tag */
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_get_tag() no or unsupported id3 tag found");
		if (opened == 1) {
			php_stream_close(stream);
		}
		return;
	}
	
	/* initialize associative return array */
	array_init(return_value);
	
	/* set version to the best one available in the file, if ID3_BEST or nothing was specified as 2nd parameter */
	if (version == ID3_BEST) {
		if (versionCheck & 0x08) {
		/* read id3v2 tag */
			version = versionCheck & 0xFC; /* see version constants to understand this */
		} else {
		/* read id3v1 tag */
			version = versionCheck & 0x03; /* see version constants to understand this */
		}
	} else {
	/* a specific tag version was requested - lets check if the tag contains this version */
		if ((version & versionCheck) != version) {
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_get_tag() specified tag-version not available - try ID3_BEST");
			if (opened == 1) {
				php_stream_close(stream);
			}
			return;
		}
	}
	
	/* call function to fill return-array depending on version */
	if (version == ID3_V1_0 || version == ID3_V1_1) {
		_php_id3v1_get_tag(stream, return_value, version TSRMLS_CC);
	} else {
		_php_id3v2_get_tag(stream, return_value, version TSRMLS_CC);
	}

	if (opened == 1) {
		php_stream_close(stream);
	}
	return;
}
/* }}} */

/* {{{ proto zval* _php_id3v1_get_tag(php_stream *stream , zval* return_value TSRMLS_DC)
   Set an array containg all information from the id3v1 tag */
void _php_id3v1_get_tag(php_stream *stream , zval* return_value, int version TSRMLS_DC)
{
	unsigned char genre;
	unsigned int bytes_read;
	char	title[31],
			artist[31],
			album[31],
			year[5],
			comment[31],
			track,
			byte28,
			byte29;
	
	/* check for v1.1 */
	php_stream_seek(stream, -3, SEEK_END);
	php_stream_read(stream, &byte28, 1);
	php_stream_read(stream, &byte29, 1);
	if (byte28 == '\0' && byte29 != '\0') {
		version = ID3_V1_1;
	}
	
	/* title */
	php_stream_seek(stream, -125, SEEK_END);
	bytes_read = php_stream_read(stream, title, 30);
	if (strlen(title) < bytes_read) {
		bytes_read = strlen(title);
	}
	add_assoc_stringl(return_value, "title", title, bytes_read, 1);

	/* artist */
	bytes_read = php_stream_read(stream, artist, 30);
	if (strlen(artist) < bytes_read) {
		bytes_read = strlen(artist);
	}
	add_assoc_stringl(return_value, "artist", artist, bytes_read, 1);

	/* album */
	bytes_read = php_stream_read(stream, album, 30);
	if (strlen(album) < bytes_read) {
		bytes_read = strlen(album);
	}
	add_assoc_stringl(return_value, "album", album, bytes_read, 1);

	/* year */
	php_stream_read(stream, year, 4);
	if (strlen(year)>0) {
		add_assoc_stringl(return_value, "year", year, 4, 1);
	}
	
	/* comment */
	if (version == ID3_V1_1) {
		bytes_read = php_stream_read(stream, comment, 28);
	} else {
		bytes_read = php_stream_read(stream, comment, 30);
	}
	if (strlen(comment) < bytes_read) {
		bytes_read = strlen(comment);
	}
	add_assoc_stringl(return_value, "comment", comment, bytes_read, 1);
	
	/* track (only possible in v1.1) */
	if (version == ID3_V1_1) {
		php_stream_seek(stream, 1, SEEK_CUR);
		php_stream_read(stream, &track, 1);
		add_assoc_long(return_value, "track", (long)track);
	}

	/* genre */
	php_stream_read(stream, &genre, 1);
	add_assoc_long(return_value, "genre", (long)genre);
}
/* }}} */

/* {{{ 
   Each id3v2-frame this extension can handle has to be registered here
   to have a mapping between frameId and PHP-array-key 
   
   !!! Remember to update global variable ID3V2_FRAMEMAP_ENTRIES when adding 
       new frame-mappings !!! */
void _php_id3v2_buildFrameMap(id3v2FrameMap *map TSRMLS_DC)
{
	long offset = 0;
	
	_php_id3v2_addFrameMap(map, offset++, "CRA", "audioEncr", "Audio encryption" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "AENC", "audioEncr", "Audio encryption" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "PIC", "attPict", "Attached picture" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "APIC", "attPict", "Attached picture" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "ASPI", "audioSeekPntIdx", "Audio seek point index" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "COM", "comment", "Comments" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "COMM", "comment", "Comments" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "COMR", "commercial", "Commercial frame" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "ENCR", "encrMethodReg", "Encryption method registration" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "EQU", "equalisation", "Equalisation" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "EQUA", "equalisation", "Equalisation" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "EQU2", "equalisation2", "Equalisation (2)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "ETC", "eventTimingCodes", "Event timing codes" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "ETCO", "eventTimingCodes", "Event timing codes" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "GEO", "encObj", "General encapsulated object" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "GEOB", "encObj", "General encapsulated object" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "GRID", "grpIdReg", "Group identification registration" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "LNK", "lnkInf", "Linked information" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "LINK", "lnkInf", "Linked information" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "MCI", "cdId", "Music CD identifier" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "MCDI", "cdId", "Music CD identifier" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "MLL", "mpgLocLookupTbl", "MPEG location lookup table" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "MLLT", "mpgLocLookupTbl", "MPEG location lookup table" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "OWNE", "ownership", "Ownership frame" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "PRIV", "private", "Private frame" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "CNT", "playCnt", "Play counter" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "PCNT", "playCnt", "Play counter" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "POP", "popularimeter", "Popularimeter" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "POPM", "popularimeter", "Popularimeter" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "POSS", "posSynch", "Position synchronisation frame" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "BUF", "recmdBufSize", "Recommended buffer size" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "RBUF", "recmdBufSize", "Recommended buffer size" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "RVA", "relVolAdj", "Relative volume adjustment" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "RVAD", "relVolAdj", "Relative volume adjustment" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "RVA2", "relVolAdj2", "Relative volume adjustment (2)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "REV", "reverb", "Reverb" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "RVRB", "reverb", "Reverb" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "SEEK", "seek", "Seek frame" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "SIGN", "signature", "Signature frame" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "SLT", "synchLyric", "Synchronised lyric/text" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "SYLT", "synchLyric", "Synchronised lyric/text" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "STC", "synchTempoCode", "Synchronised tempo codes" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "SYTC", "synchTempoCode", "Synchronised tempo codes" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "TAL", "album", "Album/Movie/Show title" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TALB", "album", "Album/Movie/Show title" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TBP", "bpm", "BPM (beats per minute)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TBPM", "bpm", "BPM (beats per minute)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TCM", "composer", "Composer" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TCOM", "composer", "Composer" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TCO", "genre", "Content type (Genre)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TCON", "genre", "Content type (Genre)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TCR", "copyright", "Copyright message" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TCOP", "copyright", "Copyright message" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TDEN", "encodingTime", "Encoding time" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TDLY", "playlistDelay", "Playlist delay" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TDOR", "orgReleaseTime", "Original release time" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TDRC", "recTime", "Recording time" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TDRL", "releaseTime", "Release time" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TDTG", "taggingTime", "Tagging time" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TEN", "encodedBy", "Encoded by" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TENC", "encodedBy", "Encoded by" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TXT", "lyricist", "Lyricist/Text writer" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TEXT", "lyricist", "Lyricist/Text writer" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TFLT", "filetype", "File type" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TIPL", "involvedPeopleList", "Involved people list" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TT1", "description", "Content group description" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TIT1", "description", "Content group description" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TT2", "title", "Title/songname/content description" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TIT2", "title", "Title/songname/content description" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TT3", "subtitle", "Subtitle/Description refinement" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TIT3", "subtitle", "Subtitle/Description refinement" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TKEY", "initialKey", "Initial key" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TLA", "language", "Language(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TLAN", "language", "Language(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TLE", "length", "Length" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TLEN", "length", "Length" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TMCL", "musicianCreditsList", "Musician credits list" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TMED", "mediaType", "Media type" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TMOO", "mood", "Mood" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOT", "originalAlbum", "Original album/movie/show title" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOAL", "originalAlbum", "Original album/movie/show title" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOF", "originalFilename", "Original filename" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOFN", "originalFilename", "Original filename" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOL", "originalLyricist", "Original lyricist(s)/text writer(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOLY", "originalLyricist", "Original lyricist(s)/text writer(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOA", "originalArtist", "Original artist(s)/performer(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOPE", "originalArtist", "Original artist(s)/performer(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TOWN", "fileOwner", "File owner/licensee" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TP1", "artist", "Lead performer(s)/Soloist(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPE1", "artist", "Lead performer(s)/Soloist(s)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TP2", "band", "Band/orchestra/accompaniment" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPE2", "band", "Band/orchestra/accompaniment" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TP3", "conductor", "Conductor/performer refinement" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPE3", "conductor", "Conductor/performer refinement" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TP4", "remixer", "Interpreted, remixed, or otherwise modified by" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPE4", "remixer", "Interpreted, remixed, or otherwise modified by" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPOS", "partOfASet", "Part of a set" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPRO", "producedNotice", "Produced notice" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPB", "publisher", "Publisher" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TPUB", "publisher", "Publisher" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TRK", "track", "Track number/Position in set" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TRCK", "track", "Track number/Position in set" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TRSN", "IRSName", "Internet radio station name" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TRSO", "IRSOwner", "Internet radio station owner" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSI", "size", "Size" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSIZ", "size", "Size" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSOA", "albumSortOrder", "Album sort order" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSOP", "performerSortOrder", "Performer sort order" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSOT", "titleSortOrder", "Title sort order" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TRC", "isrc", "ISRC (international standard recording code)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSRC", "isrc", "ISRC (international standard recording code)" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSS", "encoderSettings", "Software/Hardware and settings used for encoding" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSSE", "encoderSettings", "Software/Hardware and settings used for encoding" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TSST", "setSubtitle", "Set subtitle" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TXX", "text", "User defined text information frame" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TXXX", "text", "User defined text information frame" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TYE", "year", "Year" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "TYER", "year", "Year" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "UFI", "uniqueFileId", "Unique file identifier" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "UFID", "uniqueFileId", "Unique file identifier" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "USER", "termsOfUse", "Terms of use" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "ULT", "unsynchLyricTranscr", "Unsynchronised lyric/text transcription" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "USLT", "unsynchLyricTranscr", "Unsynchronised lyric/text transcription" TSRMLS_CC);
	
	_php_id3v2_addFrameMap(map, offset++, "WCM", "commInfo", "Commercial information" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WCOM", "commInfo", "Commercial information" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WCP", "copyrightInfo", "Copyright/Legal information" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WCOP", "copyrightInfo", "Copyright/Legal information" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WAF", "webOffAudioFile", "Official audio file webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WOAF", "webOffAudioFile", "Official audio file webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WAR", "webOffArtist", "Official artist/performer webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WOAR", "webOffArtist", "Official artist/performer webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WAS", "webOffAudioSrc", "Official audio source webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WOAS", "webOffAudioSrc", "Official audio source webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WORS", "webOffIRS", "Official Internet radio station homepage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WPAY", "payment", "Payment" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WPB", "webOffPubl", "Publishers official webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WPUB", "webOffPubl", "Publishers official webpage" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WXX", "webUserdef", "User defined URL link frame" TSRMLS_CC);
	_php_id3v2_addFrameMap(map, offset++, "WXXX", "webUserdef", "User defined URL link frame" TSRMLS_CC);
}
/* }}} */

/* {{{
   Adds an frame-key-map entry to the specified stack (registry) */
void _php_id3v2_addFrameMap(id3v2FrameMap *stack, long offset, char *frameId, char* arrayKey, char *descr TSRMLS_DC)
{
	id3v2FrameMap map;
	
	map.id		= frameId;
	map.key		= arrayKey;
	map.descr	= descr;
	
	stack[offset] = map;
	
}
/* }}} */

/* {{{ 
   Returns the PHP-array-key for a id3v2-frame-id or NULL if no matching frame-id was found */
char *_php_id3v2_getKeyForFrame(id3v2FrameMap *stack, char *frameId TSRMLS_DC)
{
	int i;
		
	for (i = 0; i < ID3V2_FRAMEMAP_ENTRIES; i++) {
		if (strcmp(stack[i].id, frameId) == 0) {
			return stack[i].key;
		}
	}
	
	return NULL;
}
/* }}} */

/* {{{ proto zval* _php_id3v1_get_tag(php_stream *stream , zval* return_value TSRMLS_DC)
   Set an array containg all information from the id3v1 tag */
void _php_id3v2_get_tag(php_stream *stream , zval* return_value, int version TSRMLS_DC)
{
	id3v2FrameMap *map;

	int	frameDataOffset,
		frameDataLength,
		frameDataLeft = 0,
		bytesRead = 0,
		currentReadPos = 0,
		paddingStart,
		paddingLength;
	
	short	paddingValid = 1,
			singleFrameLength;
		
	unsigned char	*frameData, 
					*frameContent;
	
	id3v2Header sHeader;
	id3v2ExtHeader sExtHeader;
	id3v2FrameHeader sFrameHeader;
	
	/* build frame-key-map that knows what an array-key shall be used for a specific frame */
	map = emalloc(ID3V2_FRAMEMAP_ENTRIES * sizeof(id3v2FrameMap));
	_php_id3v2_buildFrameMap(map TSRMLS_CC);
	
	sHeader 			= _php_id3v2_get_header(stream TSRMLS_CC);
	sExtHeader			= _php_id3v2_get_extHeader(stream TSRMLS_CC);
	
	frameDataOffset		= _php_id3v2_get_framesOffset(stream TSRMLS_CC);
	frameDataLength		= _php_id3v2_get_framesLength(stream TSRMLS_CC);
	singleFrameLength	= _php_id3v2_get_frameHeaderLength(sHeader.version TSRMLS_CC);
	
	php_stream_seek(stream, frameDataOffset, SEEK_SET);
	frameData 			= emalloc(frameDataLength);
	bytesRead 			= php_stream_read(stream, frameData, frameDataLength);
	
	/*
		if entire frame data is unsynched, de-unsynch it now (ID3v2.3.x)
		
		[in ID3v2.4.0] Unsynchronisation [S:6.1] is done on frame level, instead
		of on tag level, making it easier to skip frames, increasing the streamability
		of the tag. The unsynchronisation flag in the header [S:3.1] indicates that
		there exists an unsynchronised frame, while the new unsynchronisation flag in
		the frame header [S:4.1.2] indicates unsynchronisation.
	*/
	if (sHeader.version <= 3 && sHeader.flags.unsynch == 1) {
		frameDataLength	= _php_deUnSynchronize(frameData, frameDataLength TSRMLS_CC);
	}
	
	while (currentReadPos < frameDataLength) {
		frameDataLeft	= frameDataLength - currentReadPos;
	
		/* check if frame or padding */
		if (frameData[currentReadPos] != 0x00) {
		/* must be frame-data */
			sFrameHeader		= _php_id3v2_get_frameHeader(frameData, currentReadPos, sHeader.version TSRMLS_CC);
			
			/* set read-position forward after header was analyzed */
			currentReadPos	+= singleFrameLength;
			
			/* skip frame if size is lower or equal zero  */
			if (sFrameHeader.size > 0) {
				/* allocate memory for actual frame-content */
				frameContent					= emalloc(sFrameHeader.size + 1);
				frameContent[sFrameHeader.size]	= 0x00; /* to make sure the buffer is zero-terminated */
			
				_php_strnoffcpy(frameContent, frameData, currentReadPos, sFrameHeader.size TSRMLS_CC);
				
				/* delegate parsing the frame-content to specialized methods */
				if (! _php_id3v2_parseFrame(return_value, &sHeader, &sFrameHeader, frameContent, map TSRMLS_CC)) {
					/* TODO: should this throw a php-notice??? */
					//zend_printf("[DEBUG] Parsing frame %s skipped ...\n", sFrameHeader.id);
				}
				
				/* set read-position forward after header was analyzed */
				currentReadPos	+= sFrameHeader.size;
				efree(frameContent);
			} else {
				//zend_printf("[DEBUG] Frame %s skipped (size zero)\n", sFrameHeader.id);
			}
		} else {
		/* padding found */
			paddingStart	= currentReadPos;
			paddingLength	= frameDataLeft;
			
			/* check whether padding is valid */
			while (frameDataLeft) {
				if (frameData[currentReadPos++] != 0x00) {
					paddingValid = 0;
				}
				--frameDataLeft;
			}
			
			if (! paddingValid) {
				php_error_docref(NULL TSRMLS_CC, E_WARNING, "ID3v2 tag contains invalid padding - tag considered invalid");
				break;
			}
		}
	}
	
	efree(map);
	efree(frameData);
}
/* }}} */

/* {{{ 
   Delegates parsing a frame to sepcialized functions after doing 
   some general jobs like de-unsynchronization
    
	returns 1 if frame was successfully parsed, otherwise 0 */
short _php_id3v2_parseFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC)
{
	int deUnsynched;
	
	/* groupingIdentity is not yet supported by this extension */
	if (sFrameHeader->flags.groupingIdentity == 1) {
		//zend_printf("[DEBUG] NOT SUPPORTED A: %s\n", sFrameHeader->id);
		return 0;	
	}
	
	/* compression is not yet supported by this extension */
	if (sFrameHeader->flags.compression == 1) {
		//zend_printf("[DEBUG] NOT SUPPORTED B: %s\n", sFrameHeader->id);
		return 0;	
	}
	
	/* encryption is not yet supported by this extension */
	if (sFrameHeader->flags.encryption == 1) {
		//zend_printf("[DEBUG] NOT SUPPORTED C: %s\n", sFrameHeader->id);
		return 0;	
	}
	
	/* dataLengthIndicator-flag is not yet supported by this extension */
	if (sFrameHeader->flags.dataLengthIndicator == 1) {
		//zend_printf("[DEBUG] NOT SUPPORTED D: %s\n", sFrameHeader->id);
		return 0;	
	}
	
	/* if frame-content is unsynchronized it has to be de-unsynchronized now */
	if (sFrameHeader->flags.unsynch == 1) {
		/* if tag is <= v2.3 then it has been de-unsynchronized by _php_id3v2_get_tag() already 
			v2.4+ instead supports unsynchronization on frame-level
		*/
		if (sHeader->version > 3) {
			deUnsynched = _php_deUnSynchronize(frameContent, sFrameHeader->size TSRMLS_CC);
			if (deUnsynched != sFrameHeader->size) {
				return 0;
			}
		}
	}
	
	/* handle UFI[D] frame */
	if (strncmp(sFrameHeader->id, "UFI", 3) == 0) {
		return _php_id3v2_parseUFIDFrame(return_value, sHeader, sFrameHeader, frameContent, map TSRMLS_CC);
	}
	 
	/* handle text-frames (T000 - TZZZ) 
		test whether frame-id start with "T" -> 0x54 == "T" */
	if (sFrameHeader->id[0] == 0x54) {
		return _php_id3v2_parseTextFrame(return_value, sHeader, sFrameHeader, frameContent, map TSRMLS_CC);
	}
	 
	/* handle url/link-frames (W000 - WZZZ) 
		test whether frame-id start with "W" -> 0x57 == "W" */
	if (sFrameHeader->id[0] == 0x57) {
		return _php_id3v2_parseLinkFrame(return_value, sHeader, sFrameHeader, frameContent, map TSRMLS_CC);
	}

	return 0;
}
/* }}} */

/* {{{ Parses UFID-frame - Unique file identifier
   
		<Header for 'Unique file identifier', ID: "UFID" (v2.3+) - ID: "UFI" (v2.2)> 
		Owner identifier        <text string> $00
		Identifier              <up to 64 bytes binary data>
		
	returns 1 if frame-content was successfully added to the return_value, otherwise 0 */
short _php_id3v2_parseUFIDFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC)
{
	unsigned char	ownerId,
					*information;
					
	char 	*arrayKeyName;
	
	if (((sHeader->version >= 3) && (strcmp(sFrameHeader->id, "UFID") == 0)) ||
		((sHeader->version == 2) && (strcmp(sFrameHeader->id, "UFI") == 0))) {
		
		arrayKeyName = (sHeader->version == 2) ? _php_id3v2_getKeyForFrame(map, "UFI" TSRMLS_CC) : 
			_php_id3v2_getKeyForFrame(map, "UFID" TSRMLS_CC);
		if (arrayKeyName == NULL) {
			return 0;
		}
		
		ownerId		= frameContent[0];
		
		information	= emalloc(sFrameHeader->size - 1);
		_php_strnoffcpy(information, frameContent, 1, sFrameHeader->size - 1 TSRMLS_CC);
		add_assoc_stringl(return_value, arrayKeyName, information, sFrameHeader->size - 1, 1);
		efree(information);
		
		return 1;
	}
	 
	return 0;
}
/* }}} */

/* {{{ 
   Parses text-frames (T000 - TZZZ)
    
	returns 1 if frame-content was successfully added to the return_value, otherwise 0 */
short _php_id3v2_parseTextFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC)
{
	/*
		The text information frames are often the most important frames,
		containing information like artist, album and more. There may only be
		one text information frame of its kind in an tag. All text
		information frames supports multiple strings, stored as a null
		separated list, where null is reperesented by the termination code
		for the charater encoding. All text frame identifiers begin with "T".
		Only text frame identifiers begin with "T", with the exception of the
		"TXXX" frame. All the text information frames have the following
		format:
		
			<Header for 'Text information frame', ID: "T000" - "TZZZ",
			excluding "TXXX" described in 4.2.6.>
			Text encoding                $xx
			Information                  <text string(s) according to encoding>
	*/

	char	*information,
			*arrayKeyName;
			
	int		infoSize,
			i;
	
	short	encoding;
	
	/* 
		TODO: handle encoding 
	*/
	/* read the frame's encoding byte */
	encoding	= frameContent[0];
	infoSize	= sFrameHeader->size - 1;
	
	/* leave, if actual content-size (framesize - 1Byte for encoding flag) 
	   is not greater zero */
	if (! (infoSize > 0)) {
		return 0;
	}
	
	information	= emalloc(infoSize);
	_php_strnoffcpy(information, frameContent, 1, infoSize TSRMLS_CC);
	
	/*  */
	if (strncmp(sFrameHeader->id, "TXX", 3) != 0) {
		/* iterate through frameMap and check if the frames id matches one listed this map
		   if a matching pair is found, the frame's content will be added to the PHP result array */
		for (i = 0; i < ID3V2_FRAMEMAP_ENTRIES; i++) {
			if (strcmp(sFrameHeader->id, map[i].id) == 0) {
				/* retrieve array-key for this frame's entry in the PHP result array */
				if ((arrayKeyName = _php_id3v2_getKeyForFrame(map, map[i].id TSRMLS_CC)) == NULL) {
					return 0;
				}
				/* add information to the result array */
				add_assoc_stringl(return_value, arrayKeyName, information, infoSize, 1);
				efree(information);
				return 1;
			}
		}
	} else {
		/* 
			TODO: handle TXX[X] frame 
		*/
	}
	
	efree(information);
	return 0;
}
/* }}} */

/* {{{ 
   Parses url/link-frames (W000 - WZZZ)
    
	returns 1 if frame-content was successfully added to the return_value, otherwise 0 */
short _php_id3v2_parseLinkFrame(zval *return_value, id3v2Header *sHeader, id3v2FrameHeader *sFrameHeader, unsigned char *frameContent, id3v2FrameMap *map TSRMLS_DC)
{
	/*
		With these frames dynamic data such as webpages with touring
		information, price information or plain ordinary news can be added to
		the tag. There may only be one URL [URL] link frame of its kind in an
		tag, except when stated otherwise in the frame description. If the
		text string is followed by a string termination, all the following
		information should be ignored and not be displayed. All URL link
		frame identifiers begins with "W". Only URL link frame identifiers
		begins with "W", except for "WXXX". All URL link frames have the
		following format:
		
			<Header for 'URL link frame', ID: "W000" - "WZZZ", excluding "WXXX"
			described in 4.3.2.>
			URL              <text string>
	*/
	char	*arrayKeyName;
	int		i;
	
	/* leave, if actual content-size (framesize - 1Byte for encoding flag) 
	   is not greater zero */
	if (! (sFrameHeader->size > 0)) {
		return 0;
	}
	
	/*  */
	if (strncmp(sFrameHeader->id, "WXX", 3) != 0) {
		/* iterate through frameMap and check if the frames id matches one listed this map
		   if a matching pair is found, the frame's content will be added to the PHP result array */
		for (i = 0; i < ID3V2_FRAMEMAP_ENTRIES; i++) {
			if (strcmp(sFrameHeader->id, map[i].id) == 0) {
				/* retrieve array-key for this frame's entry in the PHP result array */
				if ((arrayKeyName = _php_id3v2_getKeyForFrame(map, map[i].id TSRMLS_CC)) == NULL) {
					return 0;
				}
				/* add information to the result array */
				add_assoc_stringl(return_value, arrayKeyName, frameContent, sFrameHeader->size, 1);
				return 1;
			}
		}
	} else {
		/* 
			TODO: handle WXX[X] frame 
		*/
	}
	
	return 0;
}
/* }}} */

/* {{{ 
   strncpy function that supports offsets, 
   returns the number of bytes copied */
int _php_strnoffcpy(unsigned char *dest, unsigned char *src, int offset, int len TSRMLS_DC)
{
	int i;
	
	for (i = 0; i < len; i++) {
		dest[i] = src[offset + i];
	}
	
	return i + 1;
}
/* }}} */

/* {{{ proto boolean id3_set_tag(string file, array tag [, int version])
   Set an array containg all information from the id3 tag */
PHP_FUNCTION(id3_set_tag)
{
	zval *arg;
	zval *z_array;
	php_stream *stream;
	int version = ID3_V1_0;
	int old_version = 0;
	int opened = 0;

	HashTable *array;
	char *key;
	long index;
	zval **data;
	
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "za|l", &arg, &z_array, &version) == FAILURE) {
		return;
	}

	/**
	 * v2.0 will be implemented at later point
	 */
	if (version != ID3_V1_0 && version != ID3_V1_1) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_get_tag(): Unsupported version given");
		return;
	}

	switch(Z_TYPE_P(arg)) {
		case IS_STRING:
			stream = php_stream_open_wrapper(Z_STRVAL_P(arg), "r+b", REPORT_ERRORS|ENFORCE_SAFE_MODE|STREAM_MUST_SEEK, NULL);
			opened = 1;
			break;
		case IS_RESOURCE:
			php_stream_from_zval(stream, &arg)
			break;
		default:
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_set_tag() expects parameter 1 to be string or resource");
			return;
	}

	/**
	 * invalid file
	 */
	if(!stream) {
		RETURN_FALSE;
	}

	/**
	 * No ID3v1.x tag found => append TAG and 125 zerobytes
	 * that will later store the supplied information
	 */
	old_version = _php_id3_get_version(stream TSRMLS_CC);
	if ((old_version & ID3_V1_0) == 0) {
		char blanks[125];
		php_stream_seek(stream, 0, SEEK_END);
		php_stream_write(stream, "TAG", 3);
        memset(blanks, 0, 125);
		php_stream_write(stream, blanks, 125);
	}
		
	array = HASH_OF(z_array);
	zend_hash_internal_pointer_reset(array);
	while (zend_hash_get_current_key(array, &key, &index, 0) == HASH_KEY_IS_STRING) {
		zend_hash_get_current_data(array, (void**) &data);
		
		if( strcmp("title", key) == 0) {
			convert_to_string(*data);
			if (strlen(Z_STRVAL_PP(data)) > 30) {
				php_error_docref(NULL TSRMLS_CC, E_NOTICE, "id3_set_tag(): title must be maximum of 30 characters, title gets truncated");
			}
			php_stream_seek(stream, ID3_SEEK_V1_TITLE, SEEK_END);
			php_stream_write(stream, Z_STRVAL_P(*data), 30);
		}

		if( strcmp("artist", key) == 0) {
			convert_to_string(*data);
			if (strlen(Z_STRVAL_PP(data)) > 30) {
				php_error_docref(NULL TSRMLS_CC, E_NOTICE, "id3_set_tag(): artist must be maximum of 30 characters, artist gets truncated");
			}
			php_stream_seek(stream, ID3_SEEK_V1_ARTIST, SEEK_END);
			_php_id3_write_padded(stream, data, 30 TSRMLS_CC);
		}

		if( strcmp("album", key) == 0) {
			convert_to_string(*data);
			if (strlen(Z_STRVAL_PP(data)) > 30) {
				php_error_docref(NULL TSRMLS_CC, E_NOTICE, "id3_set_tag(): album must be maximum of 30 characters, album gets truncated");
			}
			php_stream_seek(stream, ID3_SEEK_V1_ALBUM, SEEK_END);
		  	_php_id3_write_padded(stream, data, 30 TSRMLS_CC);
		}

		if( strcmp("comment", key) == 0) {
			long maxlen = 30;
			convert_to_string(*data);
			if (version == ID3_V1_1) {
				maxlen	=	28;
			}
			if (Z_STRLEN_PP(data) > maxlen) {
				php_error_docref(NULL TSRMLS_CC, E_NOTICE, "id3_set_tag(): comment must be maximum of 30 or 28 characters if v1.1 is used, comment gets truncated");
			}
			php_stream_seek(stream, ID3_SEEK_V1_COMMENT, SEEK_END);
			_php_id3_write_padded(stream, data, maxlen TSRMLS_CC);

		}

		if( strcmp("year", key) == 0) {
			convert_to_string(*data);
			if(strlen(Z_STRVAL_PP(data)) > 4) {
				php_error_docref(NULL TSRMLS_CC, E_NOTICE, "id3_set_tag(): year must be maximum of 4 characters, year gets truncated");
			}
			php_stream_seek(stream, ID3_SEEK_V1_YEAR, SEEK_END);
			_php_id3_write_padded(stream, data, 4 TSRMLS_CC);
		}

		if( strcmp("genre", key) == 0) {
			convert_to_long(*data);
			if (Z_LVAL_PP(data) > ID3_GENRE_COUNT) {
				php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_set_tag(): genre must not be greater than 147");
				zend_hash_move_forward(array);
				continue;
			}
			php_stream_seek(stream, ID3_SEEK_V1_GENRE, SEEK_END);
			php_stream_putc(stream, (char)(Z_LVAL_PP(data) & 0xFF));
		}

		if( strcmp("track", key) == 0) {
			convert_to_long(*data);
			if (version != ID3_V1_1) {
				php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_set_tag(): track may only be stored in ID3v1.1 tags");
				zend_hash_move_forward(array);
				continue;
			}
			if (Z_LVAL_PP(data) > 255) {
				php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_set_tag(): track must not be greater than 255");
				zend_hash_move_forward(array);
				continue;
			}
			php_stream_seek(stream, ID3_SEEK_V1_TRACK-1, SEEK_END);
			php_stream_putc(stream, '\0');
			php_stream_putc(stream, (char)(Z_LVAL_PP(data) & 0xFF));
		}

		zend_hash_move_forward(array);
	}
	if (opened == 1) {
		php_stream_close(stream);
	}
	
	RETURN_TRUE;
}
/* }}} */

/* {{{ write a zero-padded string to the stream */
int _php_id3_write_padded(php_stream *stream, zval **data, int length TSRMLS_DC)
{
	if (Z_STRLEN_PP(data)>length) {
		php_stream_write(stream, Z_STRVAL_PP(data), length);
	} else {	
		char blanks[30];
		memset(blanks, 0, 30);
		php_stream_write(stream, Z_STRVAL_PP(data), Z_STRLEN_PP(data));
		php_stream_write(stream, blanks, length - Z_STRLEN_PP(data));
	}
	return 1;	
}
/* }}} */

/* {{{ proto string id3_get_genre_name(int id)
 *	Returns genre name for an id */
PHP_FUNCTION(id3_get_genre_name)
{
	int id;
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &id) == FAILURE) {
		return;
	}
	if (id >= ID3_GENRE_COUNT  || id < 0) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_get_genre_name(): id must be between 0 and 147");
		return;
	}
	RETURN_STRING(id3_genres[(int)id], 1);
}
/* }}} */

/* {{{ proto int id3_get_genre_id(string name)
 *  *	Returns genre id for a genre name */
PHP_FUNCTION(id3_get_genre_id)
{
	char *name;
	int name_len;
	int i;
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &name, &name_len) == FAILURE) {
		return;
	}
	for (i = 0; i < ID3_GENRE_COUNT; i++) {
		if (strcmp(name, id3_genres[i]) == 0) {
			RETURN_LONG(i);
		}
	}
	RETURN_FALSE;
}
/* }}} */

/* {{{ proto string id3_get_frame_short_name(string frameId)
 *  *	Returns the short name for an id3v2 frame or false if no match was found */
PHP_FUNCTION(id3_get_frame_short_name)
{
	char			*frameId,
					shortName[50];
	int				frameIdLen,
					i;
	short			found = 0;
	id3v2FrameMap	*map;
	
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &frameId, &frameIdLen) == FAILURE) {
		return;
	}
	
	/* build frame-key-map that knows what an array-key shall be used for a specific frame */
	map = emalloc(ID3V2_FRAMEMAP_ENTRIES * sizeof(id3v2FrameMap));
	_php_id3v2_buildFrameMap(map TSRMLS_CC);
	
	/* look if an entry in map matches */
	for (i = 0; i < ID3V2_FRAMEMAP_ENTRIES; i++) {
		if (strcmp(frameId, map[i].id) == 0) {
			strcpy(shortName, map[i].key);
			found		= 1;
			break;
		}
	}
	
	efree(map);
	
	if (found) {
		RETURN_STRING(shortName, 1);
	}
	
	RETURN_FALSE;
}
/* }}} */

/* {{{ proto string id3_get_frame_long_name(string frameId)
 *  *	Returns the long name for an id3v2 frame or false if no match was found */
PHP_FUNCTION(id3_get_frame_long_name)
{
	char			*frameId,
					longName[100];
	int				frameIdLen,
					i;
	short			found = 0;
	id3v2FrameMap	*map;
	
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &frameId, &frameIdLen) == FAILURE) {
		return;
	}
	
	/* build frame-key-map that knows what an array-key shall be used for a specific frame */
	map = emalloc(ID3V2_FRAMEMAP_ENTRIES * sizeof(id3v2FrameMap));
	_php_id3v2_buildFrameMap(map TSRMLS_CC);
	
	/* look if an entry in map matches */
	for (i = 0; i < ID3V2_FRAMEMAP_ENTRIES; i++) {
		if (strcmp(frameId, map[i].id) == 0) {
			strcpy(longName, map[i].descr);
			found		= 1;
			break;
		}
	}
	
	efree(map);
	
	if (found) {
		RETURN_STRING(longName, 1);
	}
	
	RETURN_FALSE;
}
/* }}} */

/* {{{ proto array id3_get_genre_list()
 *  *	Returns an array with all possible genres */
PHP_FUNCTION(id3_get_genre_list)
{
	int i;
	array_init(return_value);
	for (i = 0; i < ID3_GENRE_COUNT; i++) {
		add_index_string(return_value, i, id3_genres[i], 1);
	}
	return;
}
/* }}} */

/* {{{ proto int id3_remove_tag()
 *  *	Returns true on success, otherwise false */
PHP_FUNCTION(id3_remove_tag)
{
	zval *arg;
	php_stream *stream;
	int opened = 0;
	int version = ID3_V1_0;
	int cutPos;
	int fd;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z|l", &arg, &version) == FAILURE) {
		return;
	}

	/* v2.0 will be implemented at later point */
	if (version != ID3_V1_0 && version != ID3_V1_1) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_remove_tag(): Unsupported version given");
		return;
	}

	switch (Z_TYPE_P(arg)) {
		case IS_STRING:
			stream = php_stream_open_wrapper(Z_STRVAL_P(arg), "r+b", REPORT_ERRORS|ENFORCE_SAFE_MODE|STREAM_MUST_SEEK, NULL);
			opened = 1;
			break;
		case IS_RESOURCE:
			php_stream_from_zval(stream, &arg)
			break;
		default:
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_remove_tag() expects parameter 1 to be string or resource");
			return;
	}

	/* invalid file  */
	if (!stream) {
		RETURN_FALSE;
	}

	if ((_php_id3_get_version(stream TSRMLS_CC) & ID3_V1_0) == 0) {
		php_error_docref(NULL TSRMLS_CC, E_NOTICE, "id3_remove_tag() no ID3v1 tag found");
		if (opened == 1) {
			php_stream_close(stream);
		}
		RETURN_FALSE;
	}

	php_stream_seek(stream, ID3_SEEK_V1_TAG, SEEK_END);
	cutPos = php_stream_tell(stream);
	if(cutPos == -1) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_remove_tag() was unable to remove the existing id3-tag");
		if (opened == 1) {
			php_stream_close(stream);
		}
		return;
	}

	/* using streams api for php5, otherwise a stream-cast with following ftruncate() */
	#if PHP_MAJOR_VERSION >= 5
		/* cut the stream right before TAG */
		if(php_stream_truncate_set_size(stream, cutPos) == -1) {
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_remove_tag() was unable to remove the existing id3-tag");
			if (opened == 1) {
				php_stream_close(stream);
			}
			return;
		}
	#else
		/* get the filedescriptor */
		if (FAILURE == php_stream_cast(stream, PHP_STREAM_AS_FD, (void **) &fd, REPORT_ERRORS)) {
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_remove_tag() was unable to remove the existing id3-tag");
			if (opened == 1) {
				php_stream_close(stream);
			}
			return;
		}
		/* cut the stream right before TAG */
		if(ftruncate(fd, cutPos) == -1) {
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_remove_tag() was unable to remove the existing id3-tag");
			if (opened == 1) {
				php_stream_close(stream);
			}
			return;
		}
	#endif 

	if(opened == 1) {
		php_stream_close(stream);
	}
	RETURN_TRUE;
}
/* }}} */


/* {{{ proto int id3_get_version(string file)
   Returns version of the id3 tag */
PHP_FUNCTION(id3_get_version)
{
	zval *arg;
	php_stream *stream;
	int opened = 0;
	int version = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &arg) == FAILURE) {
		return;
	}

	switch(Z_TYPE_P(arg)) {
		case IS_STRING:
			stream = php_stream_open_wrapper(Z_STRVAL_P(arg), "rb", REPORT_ERRORS|ENFORCE_SAFE_MODE|STREAM_MUST_SEEK, NULL);
			opened = 1;
			break;
		case IS_RESOURCE:
			php_stream_from_zval(stream, &arg)
			break;
		default:
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "id3_get_version() expects parameter 1 to be string or resource");
			return;
	}

	/**
	 * invalid file
	 */
	if(!stream) {
		RETURN_FALSE;
	}

	version = _php_id3_get_version(stream TSRMLS_CC);
	
	if (opened == 1) {
		php_stream_close(stream);
	}
	RETURN_LONG(version);
}
/* }}} */

/* {{{ 
   Returns a structure that contains the header-data */
id3v2Header _php_id3v2_get_header(php_stream *stream TSRMLS_DC)
{
/*
   Overall tag structure:

     +-----------------------------+
     |      Header (10 bytes)      |
     +-----------------------------+
     |       Extended Header       |
     | (variable length, OPTIONAL) |
     +-----------------------------+
     |   Frames (variable length)  |
     +-----------------------------+
     |           Padding           |
     | (variable length, OPTIONAL) |
     +-----------------------------+
     | Footer (10 bytes, OPTIONAL) |
     +-----------------------------+
   
   The first part of the ID3v2 tag is the 10 byte tag header, laid out
   as follows:

     ID3v2/file identifier      "ID3"
     ID3v2 version              $04 00
     ID3v2 flags                %abcd0000
     ID3v2 size             4 * %0xxxxxxx

   In general, padding and footer are mutually exclusive.
   
   The ID3v2 tag size is the sum of the byte length of the extended
   header, the padding and the frames after unsynchronisation. If a
   footer is present this equals to ('total size' - 20) bytes, otherwise
   ('total size' - 10) bytes.
*/

	id3v2Header sHeader;

	char	version,
			revision,
			size[5];
			
	unsigned char flags;
	
	int footer = 0;
	
	php_stream_seek(stream, 0, SEEK_SET);
	php_stream_read(stream, sHeader.id, 3);
	php_stream_read(stream, &version, 1);
	php_stream_read(stream, &revision, 1);
	php_stream_read(stream, &flags, 1);
	php_stream_read(stream, size, 4);
	
	sHeader.version = (int)version;
	sHeader.revision = (int)revision;
	
	switch (version) {
		case 2:
			/* %ab000000 in v2.2 
			 * a - Unsynchronization
			 * b - Compression
			 */
			sHeader.flags.unsynch		= BIT7((int)flags) > 0 ? 1 : 0;
			sHeader.flags.extHdr		= -1;
			sHeader.flags.experimental	= -1;
			sHeader.flags.footer		= -1;
			sHeader.flags.compression	= BIT6((int)flags) > 0 ? 1 : 0;
			break;

		case 3:
			/*
			 * %abc00000 in v2.3
			 * a - Unsynchronisation
			 * b - Extended header
			 * c - Experimental indicator
			 */
			sHeader.flags.unsynch		= BIT7((int)flags) > 0 ? 1 : 0;
			sHeader.flags.extHdr		= BIT6((int)flags) > 0 ? 1 : 0;
			sHeader.flags.experimental	= BIT5((int)flags) > 0 ? 1 : 0;
			sHeader.flags.footer		= -1;
			sHeader.flags.compression	= -1;
			break;

		case 4:
			/* %abcd0000 in v2.4
			 * a - Unsynchronisation
			 * b - Extended header
			 * c - Experimental indicator
			 * d - Footer present
			 */
			sHeader.flags.unsynch		= BIT7((int)flags) > 0 ? 1 : 0;
			sHeader.flags.extHdr		= BIT6((int)flags) > 0 ? 1 : 0;
			sHeader.flags.experimental	= BIT5((int)flags) > 0 ? 1 : 0;
			sHeader.flags.footer		= BIT4((int)flags) > 0 ? 1 : 0;
			sHeader.flags.compression	= -1;
			break;
	}
	
	if(sHeader.flags.footer == 1) {
		footer = 10;
	}
	sHeader.size 	= _php_bigEndian_to_Int(size, 4, 1 TSRMLS_CC);
	sHeader.effSize	= 10 + _php_bigEndian_to_Int(size, 4, 1 TSRMLS_CC) + footer;
	
	return sHeader;
}
/* }}} */

/* {{{ 
   Returns a structure that contains a structure that describes the extended tag-header */
id3v2ExtHeader _php_id3v2_get_extHeader(php_stream *stream TSRMLS_DC)
{
/*
   The extended header contains information that can provide further
   insight in the structure of the tag, but is not vital to the correct
   parsing of the tag information; hence the extended header is
   optional.

     Extended header size   4 * %0xxxxxxx
     Number of flag bytes       $01
     Extended Flags             $xx

   The extended flags field, with its size described by 'number of flag
   bytes', is defined as:

   %0bcd0000

	b - Tag is an update
	  	Flag data length      $00
		
	c - CRC data present
		Flag data length       $05
		Total frame CRC    5 * %0xxxxxxx
		
	d - Tag restrictions
	
		For some applications it might be desired to restrict a tag in more
		ways than imposed by the ID3v2 specification. Note that the
		presence of these restrictions does not affect how the tag is
		decoded, merely how it was restricted before encoding. If this flag
		is set the tag is restricted as follows:
	
		Flag data length       $01
		Restrictions           %ppqrrstt
	
		p - Tag size restrictionsBIT7((int)flags) > 0 ? 1 : 0;
	
		00   No more than 128 frames and 1 MB total tag size.
		01   No more than 64 frames and 128 KB total tag size.
		10   No more than 32 frames and 40 KB total tag size.
		11   No more than 32 frames and 4 KB total tag size.
	
		q - Text encoding restrictions
	
		0    No restrictions
		1    Strings are only encoded with ISO-8859-1 [ISO-8859-1] or
			UTF-8 [UTF-8].
	
		r - Text fields size restrictions
	
		00   No restrictions
		01   No string is longer than 1024 characters.
		10   No string is longer than 128 characters.
		11   No string is longer than 30 characters.
	
		Note that nothing is said about how many bytes is used to
		represent those characters, since it is encoding dependent. If a
		text frame consists of more than one string, the sum of the
		strungs is restricted as stated.
	
		s - Image encoding restrictions
	
		0   No restrictions
		1   Images are encoded only with PNG [PNG] or JPEG [JFIF].
	
		t - Image size restrictions
	
		00  No restrictions
		01  All images are 256x256 pixels or smaller.
		10  All images are 64x64 pixels or smaller.
		11  All images are exactly 64x64 pixels, unless required
			otherwise.
*/

	id3v2ExtHeader sExtHdr;
	
	char	size[5],
			numFlagBytes,
			extFlags,
			crcData[6],
			tagRestrictions;
			
	int 	iTRByte;
		
	php_stream_seek(stream, ID3V2_BASEHEADER_LENGTH, SEEK_SET);
	
	php_stream_read(stream, size, 4);
	php_stream_read(stream, &numFlagBytes, 1);
	php_stream_read(stream, &extFlags, 1);
	
	sExtHdr.size 				= _php_bigEndian_to_Int(size, 4, 1 TSRMLS_CC);
	sExtHdr.numFlagBytes		= (int)numFlagBytes;
	
	sExtHdr.flags.update		= BIT6((int)extFlags) > 0 ? 1 : 0;
	sExtHdr.flags.crcPresent	= BIT5((int)extFlags) > 0 ? 1 : 0;
	sExtHdr.flags.restrictions	= BIT4((int)extFlags) > 0 ? 1 : 0;
	
	if (sExtHdr.flags.crcPresent == 1) {
		php_stream_read(stream, crcData, 5);
		sExtHdr.flags.crcData = _php_bigEndian_to_Int(crcData, 5, 1 TSRMLS_CC);
	}
	
	if (sExtHdr.flags.restrictions == 1) {
		php_stream_read(stream, &tagRestrictions, 1);
		
		iTRByte = (int)tagRestrictions;
		sExtHdr.flags.restrictionData.tagSize		= (iTRByte & 0x00C0) >> 6;
		sExtHdr.flags.restrictionData.textEncoding	= (iTRByte & 0x0020) >> 5;
		sExtHdr.flags.restrictionData.textFieldSize	= (iTRByte & 0x0018) >> 3;
		sExtHdr.flags.restrictionData.imageEncoding	= (iTRByte & 0x0004) >> 2;
		sExtHdr.flags.restrictionData.imageSize		= (iTRByte & 0x0003);
	}
	
	//php_stream_read(stream, &version, 1);
	return sExtHdr;
}
/* }}} */

/* {{{ 
   Returns a structure that contains the frame's header-data */
id3v2FrameHeader _php_id3v2_get_frameHeader(unsigned char *data, int offset, short version TSRMLS_DC)
{
	id3v2FrameHeader sFrameHeader;
	id3v2FrameHeaderFlags sFlags;
	
	int frameDataLength;
	
	unsigned char 	*frameData,
					*size;
	
	sFlags.tagAlterPreservation		= -1;
	sFlags.fileAlterPreservation	= -1;
	sFlags.readOnly					= -1;
	sFlags.groupingIdentity			= -1;
	sFlags.groupId					= -1;
	sFlags.compression				= -1;
	sFlags.encryption				= -1;
	sFlags.encryptMethod			= -1;
	sFlags.unsynch					= -1;
	sFlags.dataLengthIndicator		= -1;
	sFlags.dataLength				= -1;

	frameDataLength	= _php_id3v2_get_frameHeaderLength(version TSRMLS_CC);
	frameData		= emalloc(frameDataLength);
	
	/* copy relevant frame data */
	_php_strnoffcpy(frameData, data, offset, frameDataLength TSRMLS_CC);
	
	if (version == 2) {
		/*
		 Frame ID  $xx xx xx (three characters)
		 Size      $xx xx xx (24-bit integer)
		*/
		strncpy(sFrameHeader.id, frameData, 3);
		size = emalloc(3);
		size[0] = frameData[3]; size[1] = frameData[4]; size[2] = frameData[5];
		sFrameHeader.size = _php_bigEndian_to_Int(size, 3, 0 TSRMLS_CC);
		
		/* no flags in 2.2 */ 

	} else if (version > 2) {
		/*
		 Frame ID  $xx xx xx xx (four characters)
		 Size      $xx xx xx xx (32-bit integer in 2.3, 28-bit synchsafe integer in 2.4)
		 Flags     $xx xx
		*/
		
		/* frame-id */
		strncpy(sFrameHeader.id, frameData, 4);
		sFrameHeader.id[4] = 0x00;
		
		/* frame-size */
		size = emalloc(4);
		size[0] = frameData[4]; 
		size[1] = frameData[5]; 
		size[2] = frameData[6]; 
		size[3] = frameData[7];
		
		if (version == 3) {
			/* v2.3 -> 32bit integer */
			sFrameHeader.size = _php_bigEndian_to_Int(size, 4, 0 TSRMLS_CC);
		} else {
			/* v2.4+ -> 32bit synchsafe integer (28bit value) */
			sFrameHeader.size = _php_bigEndian_to_Int(size, 4, 1 TSRMLS_CC);
		}

		/* 
			Frame header flags

			In the frame header the size descriptor is followed by two flag
			bytes. All unused flags MUST be cleared. The first byte is for
			'status messages' and the second byte is a format description. If an
			unknown flag is set in the first byte the frame MUST NOT be changed
			without that bit cleared. If an unknown flag is set in the second
			byte the frame is likely to not be readable. Some flags in the second
			byte indicates that extra information is added to the header. These
			fields of extra information is ordered as the flags that indicates
			them. The flags field is defined as follows (l and o left out because
			ther resemblence to one and zero):
			
				%0abc0000 %0h00kmnp
			
			Some frame format flags indicate that additional information fields
			are added to the frame. This information is added after the frame
			header and before the frame data in the same order as the flags that
			indicates them. I.e. the four bytes of decompressed size will precede
			the encryption method byte. These additions affects the 'frame size'
			field, but are not subject to encryption or compression.
			
			The default status flags setting for a frame is, unless stated
			otherwise, 'preserved if tag is altered' and 'preserved if file is
			altered', i.e. %00000000.
			
			Frame status flags:
			
			a - Tag alter preservation
			
				This flag tells the tag parser what to do with this frame if it is
				unknown and the tag is altered in any way. This applies to all
				kinds of alterations, including adding more padding and reordering
				the frames.
			
				0     Frame should be preserved.
				1     Frame should be discarded.
			
			
			b - File alter preservation
			
				This flag tells the tag parser what to do with this frame if it is
				unknown and the file, excluding the tag, is altered. This does not
				apply when the audio is completely replaced with other audio data.
			
				0     Frame should be preserved.
				1     Frame should be discarded.
			
			
			c - Read only
			
				This flag, if set, tells the software that the contents of this
				frame are intended to be read only. Changing the contents might
				break something, e.g. a signature. If the contents are changed,
				without knowledge of why the frame was flagged read only and
				without taking the proper means to compensate, e.g. recalculating
				the signature, the bit MUST be cleared.
			
			
			Frame format flags:
			
			h - Grouping identity
			
				This flag indicates whether or not this frame belongs in a group
				with other frames. If set, a group identifier byte is added to the
				frame. Every frame with the same group identifier belongs to the
				same group.
			
				0     Frame does not contain group information
				1     Frame contains group information
			
			
			k - Compression
			
				This flag indicates whether or not the frame is compressed.
				A 'Data Length Indicator' byte MUST be included in the frame.
			
				0     Frame is not compressed.
				1     Frame is compressed using zlib [zlib] deflate method.
						If set, this requires the 'Data Length Indicator' bit
						to be set as well.
			
			
			m - Encryption
			
				This flag indicates whether or not the frame is encrypted. If set,
				one byte indicating with which method it was encrypted will be
				added to the frame. See description of the ENCR frame for more
				information about encryption method registration. Encryption
				should be done after compression. Whether or not setting this flag
				requires the presence of a 'Data Length Indicator' depends on the
				specific algorithm used.
			
				0     Frame is not encrypted.
				1     Frame is encrypted.
			
			n - Unsynchronisation
			
				This flag indicates whether or not unsynchronisation was applied
				to this frame. See section 6 for details on unsynchronisation.
				If this flag is set all data from the end of this header to the
				end of this frame has been unsynchronised. Although desirable, the
				presence of a 'Data Length Indicator' is not made mandatory by
				unsynchronisation.
			
				0     Frame has not been unsynchronised.
				1     Frame has been unsyrchronised.
			
			p - Data length indicator
			
				This flag indicates that a data length indicator has been added to
				the frame. The data length indicator is the value one would write
				as the 'Frame length' if all of the frame format flags were
				zeroed, represented as a 32 bit synchsafe integer.
			
				0      There is no Data Length Indicator.
				1      A data length Indicator has been added to the frame.
		*/

		/* status flags */
		sFlags.tagAlterPreservation		= BIT6((int)frameData[8]) > 0 ? 1 : 0;
		sFlags.fileAlterPreservation	= BIT5((int)frameData[8]) > 0 ? 1 : 0;
		sFlags.readOnly					= BIT4((int)frameData[8]) > 0 ? 1 : 0;
		
		/* format flags */
		sFlags.groupingIdentity			= BIT6((int)frameData[9]) > 0 ? 1 : 0;
		sFlags.compression				= BIT3((int)frameData[9]) > 0 ? 1 : 0;
		sFlags.encryption				= BIT2((int)frameData[9]) > 0 ? 1 : 0;
		sFlags.unsynch					= BIT1((int)frameData[9]) > 0 ? 1 : 0;
		sFlags.dataLengthIndicator		= BIT0((int)frameData[9]) > 0 ? 1 : 0;
		
		sFrameHeader.flags	= sFlags;
	}
	
	efree(size);
	efree(frameData);
	
	return sFrameHeader;
}
/* }}} */

/* {{{ 
   Returns the length in bytes of the id3v2-tag */
int _php_id3v2_get_framesOffset(php_stream *stream TSRMLS_DC)
{
	int offset = 0;

	id3v2Header sHeader = _php_id3v2_get_header(stream TSRMLS_CC);
	id3v2ExtHeader sExtHdr;
	
	/* if no extended header is present the frames will start directly after the header */
	if (sHeader.flags.extHdr != 1) {
		return offset = 10;
	}
	
	/* when this is executed an extended header is present */
	offset += 10;
	sExtHdr = _php_id3v2_get_extHeader(stream TSRMLS_CC);
	return offset += sExtHdr.size;
}
/* }}} */

/* {{{
   Returns the length of the frame-block in bytes */
int _php_id3v2_get_framesLength(php_stream* stream TSRMLS_DC)
{
	int frameDataLength = 0;
	
	id3v2Header sHeader;
	id3v2ExtHeader sExtHeader;
	
	sHeader = _php_id3v2_get_header(stream TSRMLS_CC);

	frameDataLength	= sHeader.size;
	if (sHeader.flags.extHdr == 1) {
		sExtHeader = _php_id3v2_get_extHeader(stream TSRMLS_CC);
		frameDataLength -= sExtHeader.size;
	}
	
	return frameDataLength;
}
/* }}} */

/* {{{
   Returns the version-number (see the version constants above) */
int _php_id3_get_version(php_stream *stream TSRMLS_DC)
{
	int		version = 0;
	char 	buf[4],
			majorVersion = 0,
			revision = 0;

	/**
	 * check for v1
	 */
	php_stream_seek(stream, ID3_SEEK_V1_TAG, SEEK_END);
	php_stream_read(stream, buf, 3);
	if (strncmp("TAG", buf, 3) == 0) {
		char byte28, byte29;
		version = version | ID3_V1_0;


		php_stream_seek(stream, -3, SEEK_END);
		php_stream_read(stream, &byte28, 1);
		php_stream_read(stream, &byte29, 1);
		if (byte28 == '\0' && byte29 != '\0') {
			version = version | ID3_V1_1;
		}
	}

	/**
	 * check for v2
	 */
	php_stream_seek(stream, 0, SEEK_SET);
	php_stream_read(stream, buf, 3);
	if (strncmp("ID3", buf, 3) == 0) {
		php_stream_read(stream, &majorVersion, 1);
		php_stream_read(stream, &revision, 1);
		
		switch ((int)majorVersion) {
			case 1:
				version = version | ID3_V2_1;
				break;
			case 2:
				version = version | ID3_V2_2;
				break;
			case 3:
				version = version | ID3_V2_3;
				break;
			case 4:
				version = version | ID3_V2_4;
				break;
		}
	}
	return version;
}
/* }}} */

/* {{{ 
   Converts a big-endian byte-stream into an integer */
int _php_bigEndian_to_Int(char* byteword, int bytewordlen, short synchsafe TSRMLS_DC) 
{
	int	intvalue	= 0,
		i;
	
	for (i = 0; i < bytewordlen; i++) {
		if (synchsafe) { 
		/* don't care about MSB, effectively 7bit bytes */
			intvalue = intvalue | ((int)byteword[i] & 0x7F) << ((bytewordlen - 1 - i) * 7);
		} else {
			intvalue += (int)byteword[i] * (int)pow(256, (bytewordlen - 1 - i));
		}
	}
	return intvalue;
}
/* }}} */

/* {{{ 
   De-Unsynchronizes the data in a specified buffer. 
   Returns the bufferlength after de-unsynchronization */
int _php_deUnSynchronize(unsigned char* buf, int bufLen TSRMLS_DC) 
{
	int	i,
		j,
		newBufLen = bufLen;
		
	unsigned char *newBuf;
	
	for (i = 0; i < bufLen; i++) {
		if ((int)buf[i] == 0xFF) {
			++newBufLen;
		}
	}
	
	/* if no bytes have to be modified, I can leave now */
	if (newBufLen == bufLen) {
		return newBufLen;
	}
	
	/* lets go and de-unsynchronize some bytes */
	newBuf = emalloc(newBufLen);
	
	for (i = 0, j = 0; i < bufLen; i++, j++) {
		if ((int)buf[i] != 0xFF) {
			newBuf[j]	= buf[i];	
		} else {
			newBuf[j]	= buf[i];
			newBuf[++j]	= 0x00;
		}
	}

	buf = newBuf;
	efree(newBuf);
	return newBufLen;
}
/* }}} */

/* {{{ 
   Returns the frame-header length depending on id3v2 major-version */
short _php_id3v2_get_frameHeaderLength(short majorVersion TSRMLS_DC)
{
	return ((majorVersion == 2) ? 6 : 10);
}
/* }}} */

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
