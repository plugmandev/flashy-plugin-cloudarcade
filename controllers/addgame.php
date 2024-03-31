<?php

session_start();
require_once('../../../../config.php');
require_once('../../../../init.php');

$action = isset( $_POST['action'] ) ? $_POST['action'] : "";
$username = isset( $_SESSION['username'] ) ? $_SESSION['username'] : "";

if ( $action != "login" && $action != "logout" && !$username ) {
	exit('logout');
}

if( !USER_ADMIN ){
	exit('p');
}

if (!file_exists('../tmp')) {
	mkdir('../tmp', 0755, true);
}
if (!file_exists('../../../../games')) {
	mkdir('../../../../games', 0755, true);
}
$target_dir = "../tmp/";
$target_file = $target_dir . strtolower(str_replace(' ', '-', basename($_FILES["gamefile"]["name"])));
$folder_name = 0;
if(isset($_POST['slug'])){
	$_POST['slug'] = esc_slug($_POST['slug']);
	$folder_name = $_POST['slug'];
} else {
	$folder_name = esc_slug($_POST['title']);
}

$uploadOk = 1;
$error = "Something went wrong!";

if (isset($_SERVER['CONTENT_LENGTH'])) {
	if($_SERVER['CONTENT_LENGTH'] > convert_to_bytes(ini_get('upload_max_filesize'))){
		$uploadOk = 0;
		$error = 'You file size is too large, your php.ini upload_max_filesize is '.ini_get('upload_max_filesize');
	}
}

function convert_to_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
if($fileType != 'zip'){
	$uploadOk = 0;
}

$have_icon_512 = false; //Used for Construct 3 game
$generate_thumbnail = false;

if ($uploadOk == 0) {
  echo json_encode([
	"status" => 500,
	"message" => false,
	"data" => false
  ]);
} else {
  if (move_uploaded_file($_FILES["gamefile"]["tmp_name"], $target_file)) {
  	$check = array();
	$check['index'] = 'false';
	$check['thumb_1'] = false;
	$check['thumb_2'] = false;
	//uploaded
	$za = new ZipArchive();
	$za->open($target_file);
	for( $i = 0; $i < $za->numFiles; $i++ ){
		$stat = $za->statIndex( $i );
		$name = $stat['name'];
		if($name == 'game.swf'){
			$check['index'] = $name;
		}
		if($name == 'thumb_1.png' || $name == 'thumb_1.jpg' || $name == 'thumb_1.jpeg' || $name == 'thumb_1.PNG' || $name == 'thumb_1.JPG'){
			if(!$check['thumb_1']){
				$check['thumb_1'] = $name;
			}
		}
		if($name == 'thumb_2.png' || $name == 'thumb_2.jpg' || $name == 'thumb_2.jpeg' || $name == 'thumb_2.PNG' || $name == 'thumb_2.JPG'){
			if(!$check['thumb_2']){
				$check['thumb_2'] = $name;
			}
		}
		if($name == 'icons/icon-512.png'){
			$have_icon_512 = true;
		}
	}
	if(!$check['thumb_1'] && !$check['thumb_2'] && $have_icon_512){
		$check['thumb_1'] = 'thumb_1.png';
		$check['thumb_2'] = 'thumb_2.png';
		$generate_thumbnail = true;
	}
	$za->close();
  } else {
	echo json_encode([
		"status" => 500,
		"message" => false,
		"data" => false
	]);
  }
}

if($uploadOk == 1){
	if(!$check['index']){
		$error = 'No game.swf on root detected!';
		$uploadOk = 0;
	}
	if(!$check['thumb_1']){
		$error = 'No thumb_1.jpg/png on root detected!';
		$uploadOk = 0;
	}
	if(!$check['thumb_2']){
		$error = 'No thumb_2.jpg/png on root detected!';
		$uploadOk = 0;
	}
}
if($uploadOk == 0){
	unlink($target_file);
	// Store current fields
	$keys =['title', 'slug', 'description', 'instructions', 'width', 'height', 'category', 'thumb_1', 'thumb_2', 'url', 'tags'];
	foreach ($keys as $item) {
		$_SESSION[$item] = (isset($_POST[$item])) ? $_POST[$item] : null;
	}

	echo json_encode([
		"status" => 500,
		"message" => $error,
		"data" => false
	]);
} else {
	$zip = new ZipArchive;
	$res = $zip->open($target_file);
	if ($res === TRUE) {
		$zip->extractTo('../../../../games/plugman/flash/'.$folder_name.'/');
		$zip->close();
	} else {
		echo json_encode([
			"status" => 500,
			"message" => false,
			"data" => false
		]);
	}
	unlink($target_file);
	if($generate_thumbnail){
		require_once('../../../../includes/commons.php');
		// Begin generate thumbnail
		try {
			$target_img = '../../../../games/plugman/flash/'.$folder_name.'/icons/icon-512.png';
			if(file_exists($target_img)){
				imgCopy($target_img, '../../../../games/plugman/flash/'.$folder_name.'/thumb_1.png', 512, 384);
				imgCopy($target_img, '../../../../games/plugman/flash/'.$folder_name.'/thumb_2.png', 512, 512);
			}
		} catch(Exception $e) {
			var_dump($e);
		}
	}
	$cats = '';
	$i = 0;
	$total = count($_POST['category']);
	foreach ($_POST['category'] as $key) {
		$cats = $cats.$key;
		if($i < $total-1){
			$cats = $cats.',';
		}
		$i++;
	}
	$_POST['ref'] = 'upload';
	$_POST['action'] = 'addGame';
	$_POST['category'] = $cats;
	$_POST['thumb_1'] = '/games/plugman/flash/'.$folder_name.'/'.$check['thumb_1'];
	$_POST['thumb_2'] = '/games/plugman/flash/'.$folder_name.'/'.$check['thumb_2'];
	$_POST['url'] = '/games/plugman/flash/'.$folder_name.'/';
	if( SMALL_THUMB ){
		$output = pathinfo($check['thumb_2']);
		$_POST['thumb_small'] = '/games/plugman/flash/'.$folder_name.'/'.$folder_name.'_small.'.$output['extension'];
		imgResize('..'.$_POST['thumb_2'], 160, 160, $folder_name);
	}
	
	add_game();

	echo json_encode([
		"status" => 200,
		"message" => false,
		"data" => false
	]);

	die;
}

function add_game(){
	$ref = '';
	if(isset($_POST['ref'])) $ref = $_POST['ref'];
	$_POST['description'] = html_purify($_POST['description']);
	$_POST['instructions'] = html_purify($_POST['instructions']);
	if($_POST['source'] == 'self' || $_POST['source'] == 'remote'){
		if(!isset($_POST['published'])){
			$_POST['published'] = false;
		}
	}
	if(!isset($_POST['is_mobile'])){
		$_POST['is_mobile'] = false;
	}
	$redirect = 0;
	if(isset($_POST['redirect'])){
		$redirect = $_POST['redirect'];
	}
	if(isset($_POST['slug'])){
		$slug = esc_slug($_POST['slug']);
	} else {
		$slug = esc_slug(strtolower(str_replace(' ', '-', $_POST["title"])));
	}
	$slug = preg_replace('/-{2,}/', '-', $slug);
	$slug = trim($slug, '-');
	$_POST['slug'] = $slug;
	if(is_array($_POST['category'])){
		// Array category is not allowed
		// Convert to string
		$cats = '';
		$i = 0;
		$total = count($_POST['category']);
		foreach ($_POST['category'] as $key) {
			$cats = $cats.$key;
			if($i < $total-1){
				$cats = $cats.',';
			}
			$i++;
		}
		$_POST['category'] = $cats;
	}
	if($_POST['category'] == '' || $_POST['category'] == ' '){
		$_POST['category'] = 'Other';
	}
	// Begin category filter
	if(file_exists(ABSPATH."content/plugins/category-filter")){
		// Plugin exist
		$cats = '';
		$categories = commas_to_array($_POST['category']);
		$i = 0;
		$total = count($categories);
		foreach ($categories as $key) {
			$cats = $cats.category_name_filtering($key);
			if($i < $total-1){
				$cats = $cats.',';
			}
			$i++;
		}
		$_POST['category'] = $cats;
	}
	$game = new Game;
	$check=$game->getBySlug($slug);
	$status='failed';
	if(is_null($check)){
		if($ref != 'upload'){
			// Come from fetch games, json importer or remote add
			if(IMPORT_THUMB){
				// Check if webp is activated
				$use_webp = get_setting_value('webp_thumbnail');
				import_thumbnail($_POST['thumb_2'], $slug, 2);
				$name = basename($_POST['thumb_2']);
				$extension = pathinfo($_POST['thumb_2'], PATHINFO_EXTENSION);
				$_POST['thumb_2'] = '/thumbs/'.$slug.'_2.'.$extension;
				if($use_webp){
					$_POST['thumb_2'] = str_replace('.'.$extension, '.webp', $_POST['thumb_2']);
				}
				//
				import_thumbnail($_POST['thumb_1'], $slug, 1);
				$name = basename($_POST['thumb_1']);
				$extension = pathinfo($_POST['thumb_1'], PATHINFO_EXTENSION);
				$_POST['thumb_1'] = '/thumbs/'.$slug.'_1.'.$extension;
				if($use_webp){
					$_POST['thumb_1'] = str_replace('.'.$extension, '.webp', $_POST['thumb_1']);
				}
				if( SMALL_THUMB ){
					$output = pathinfo($_POST['thumb_2']);
					$_POST['thumb_small'] = '/thumbs/'.$slug.'_small.'.$output['extension'];
					if($use_webp){
						$file_extension = pathinfo($_POST['thumb_2'], PATHINFO_EXTENSION);
						$_POST['thumb_small'] = str_replace('.'.$file_extension, '.webp', $_POST['thumb_small']);
						generate_small_thumbnail($_POST['thumb_2'], $slug);
					} else {
						generate_small_thumbnail($_POST['thumb_2'], $slug);
					}
				}
			}
		}
		$game->storeFormValues( $_POST );
		$game->insert();
		$status='added';
		//
		$cats = commas_to_array($_POST['category']);
		if(is_array($cats)){ //Add new category if not exist
			$length = count($cats);
			for($i = 0; $i < $length; $i++){
				$_POST['name'] = $cats[$i];
				$category = new Category;
				$exist = $category->isCategoryExist($_POST['name']);
				if($exist){
				  //
				} else {
					unset($_POST['slug']);
					$_POST['description'] = '';
					$category->storeFormValues( $_POST );
					$category->insert();
				}
				$category->addToCategory($game->id, $category->id);
			}
		}
	}
	else{
		$status='exist';
	}

	$keys =['title', 'slug', 'description', 'instructions', 'width', 'height', 'category', 'thumb_1', 'thumb_2', 'url', 'tags'];
	if($status != 'added'){
		if(($_POST['source'] == 'self' || $_POST['source'] == 'remote') && !isset($_POST['dont_store_fields'])){
			// Store current fields
			foreach ($keys as $item) {
				$_SESSION[$item] = (isset($_POST[$item])) ? $_POST[$item] : null;
			}
		}
	} else {
		// Successfully added
		// Clear last fields
		if(isset($_SESSION['title'])){
			foreach ($keys as $item) {
				if(isset($_SESSION[$item])){
					unset($_SESSION[$item]);
				}
			}
		}
	}
}