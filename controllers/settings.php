<?php

session_start();

require '../../../../config.php';
require '../../../../init.php';
require '../plugman.php';

if( !USER_ADMIN ){
	exit('p');
}

$request = $_POST;

if(isset($request['pcode'], $request['lowquality'], $request['savefiles'])) {
    $pcode = filter_input(INPUT_POST, 'pcode');
    $lowquality = filter_input(INPUT_POST, 'lowquality', FILTER_VALIDATE_INT);
    $saveFiles = filter_input(INPUT_POST, 'savefiles', FILTER_VALIDATE_INT);
    
    $logoFileName = '';
    $bannerFileName = '';
    $uploadPath = '../uploads/';
    
    if(isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logoFileName = $_FILES['logo']['name'];
        $logoTempName = $_FILES['logo']['tmp_name'];
        $logoFileSize = $_FILES['logo']['size'];
        $logoFileType = $_FILES['logo']['type'];
        
        $allowedExtensions = array('png');
        $maxFileSize = 2 * 1024 * 1024; 
        
        $logoFileExtension = pathinfo($logoFileName, PATHINFO_EXTENSION);
        
        if(!in_array(strtolower($logoFileExtension), $allowedExtensions)) {
            echo json_encode([
                "status" => 500,
                "message" => "Invalid file format for logo. Only PNG files are allowed",
                "data" => false
            ]);
        }
        
        if($logoFileSize > $maxFileSize) {
            echo json_encode([
                "status" => 500,
                "message" => "Logo file size exceeds the maximum limit (2MB)",
                "data" => false
            ]);
        }
        
        $logoFinalPath = $uploadPath . $logoFileName;
        move_uploaded_file($logoTempName, $logoFinalPath);
    }

    if(isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $bannerFileName = $_FILES['banner']['name'];
        $bannerTempName = $_FILES['banner']['tmp_name'];
        $bannerFileSize = $_FILES['banner']['size'];
        $bannerFileType = $_FILES['banner']['type'];
        
        $allowedExtensions = array('png');
        $maxFileSize = 2 * 1024 * 1024; 
        
        $bannerFileExtension = pathinfo($bannerFileName, PATHINFO_EXTENSION);
        
        if(!in_array(strtolower($bannerFileExtension), $allowedExtensions)) {
            echo json_encode([
                "status" => 500,
                "message" => "Invalid file format for banner. Only PNG files are allowed",
                "data" => false
            ]);
        }
        
        if($bannerFileSize > $maxFileSize) {
            echo json_encode([
                "status" => 500,
                "message" => "Banner file size exceeds the maximum limit (2MB)",
                "data" => false
            ]);
        }
        
        $bannerFinalPath = $uploadPath . $bannerFileName;
        move_uploaded_file($bannerTempName, $bannerFinalPath);
    }

    try {
        $oldCode = get_setting_value('plugman_flashy_pcode');
    } catch (Exception $e) {
        $oldCode = null;
    }

    try {
        $oldLogo = get_setting_value('plugman_flashy_logo');
    } catch (Exception $e) {
        $oldLogo = null;
    }

    try {
        $oldBanner = get_setting_value('plugman_flashy_banner');
    } catch (Exception $e) {
        $oldBanner = null;
    }
    
    plugman_update_setting('plugman_flashy_pcode', $pcode);
    plugman_update_setting('plugman_flashy_lowquality', $lowquality); 
    plugman_update_setting('plugman_flashy_savefiles', $saveFiles); 

    if (!empty($logoFileName)) {
        plugman_update_setting('plugman_flashy_logo', $logoFileName);
        @unlink($uploadPath . $oldLogo);
    }
    
    if (!empty($bannerFileName)) {
        plugman_update_setting('plugman_flashy_banner', $bannerFileName); 
        @unlink($uploadPath . $oldBanner);
    }
    
    if($oldCode == $pcode) {
        $httpCode = 200;
    } else {
        $httpCode = 302;
        plugman_update_setting('plugman_flashy_token', false);
    }
    
    echo json_encode([
        "status" => $httpCode,
        "message" => "Settings updated successfully!",
        "data" => false
    ]);
} else {
    echo json_encode([
        "status" => 500,
        "message" => "Invalid Request!",
        "data" => false
    ]);
}