<?PHP
echo "from file\n";
$v= id3_get_version('/home/schst/demo.mp3');
var_dump($v);

echo "from resource\n";
$v = id3_get_version(fopen('/home/schst/demov2.mp3', 'rb'));
var_dump($v);
?>
