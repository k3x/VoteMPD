<?PHP
$frame	= 'TOLY';

$short	= id3_get_frame_short_name($frame);
$long	= id3_get_frame_long_name($frame);

var_dump($frame);
var_dump($short);
var_dump($long);
?>
