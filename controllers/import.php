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

try {
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

    echo json_encode([
        "status" => 200,
        "message" => false,
        "data" => false
    ]);
} catch(Exception $e) {
    echo json_encode([
        "status" => 500,
        "message" => false,
        "data" => false
    ]);
}