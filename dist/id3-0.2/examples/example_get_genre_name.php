<?PHP
/**
 * inavlid IDs
 */
$name = id3_get_genre_name(-40);
$name = id3_get_genre_name(148);

/**
 * valid id
 */
$name = id3_get_genre_name(20);
var_dump($name);

$id = id3_get_genre_id($name);
var_dump($id);


$list = id3_get_genre_list();
var_dump($list);
?>
