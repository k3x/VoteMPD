<?PHP
error_reporting(E_ALL);

$array = array(
				"title"  => "This is the title!",
				"artist" => "No one",
				"album"  => "Test",
				"year"   => 2004,
				"genre"  => 23,
				"comment" => "FOOOOO",
				"track"  => 20
			);
$result = id3_set_tag( "/home/schst/demo_new.mp3", $array, ID3_V1_1 );
var_dump($result);

//$result = id3_get_version("/home/schst/.plan");
$result = id3_set_tag( "/home/schst/.plan", $array);
var_dump($result);
?>
