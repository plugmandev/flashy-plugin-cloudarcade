<?php
/**
 * Plugin Name: Flashy
 * Description: A plugin to integrate Flash games to your CloudArcade websites.
 * Author: PlugMan <mail@plugman.dev>
 */

if(!defined('USER_ADMIN')){
    exit;
}

define("plugman_base", "https://api.plugman.dev");
define("plugman_version", "v1");
define("plugman_api", plugman_base . "/flashy/" . plugman_version);
define("plugman_site_url", "//" . (isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : php_uname("n")) . (in_array($_SERVER["SERVER_PORT"], [80, 443]) ? false : ":{$_SERVER["SERVER_PORT"]}") . (empty(explode("/", dirname($_SERVER["SCRIPT_NAME"]))[1]) ? false : dirname($_SERVER["SCRIPT_NAME"])));

function plugman_site_url($relativeUrl = "") {
    $baseUrl = plugman_site_url;

    // Parse the base URL
    $parsedBaseUrl = parse_url($baseUrl);

    // Extract the constant part of the path
    $constantPath = isset($parsedBaseUrl['path']) ? trim($parsedBaseUrl['path'], '/') : '';

    // Remove any paths between the constant part and the relative URL
    $relativePath = preg_replace('#^/?' . $constantPath . '/?#', '', ltrim($relativeUrl, "/"));

    // Rebuild the URL with the constant part and the modified relative path
    $mergedUrl = "//{$parsedBaseUrl['host']}/{$relativePath}";

    return $mergedUrl;
}

/**
 * Function to make a GET request to a specified URL with array parameters using cURL.
 *
 * @param string $url The URL to which the request will be made.
 * @param array $params An associative array of parameters to be included in the request.
 * @return string|bool The response from the server, or FALSE on failure.
 */
function plugmanApi($path, $params = array()) {
    $url = plugman_api . $path;

    $queryString = http_build_query($params);

    if (!empty($queryString)) {
        $url .= '?' . $queryString;
    }

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($curl);

    if(curl_errno($curl)) {
        $error_msg = curl_error($curl);
        return false;
    }

    curl_close($curl);

    return json_decode($response, true);
}

/**
 * Function to update a setting in the database.
 * @param string $name The name of the setting.
 * @param string $value The value of the setting.
 * @param bool $ignore Whether to ignore the setting if it does not exist.
 */

function plugman_update_setting($name, $value, $ignore = false) {
    $conn = open_connection();
    
    // Check if the setting exists, if not, create it
    if (!setting_exists($conn, $name)) {
        create_setting($conn, $name, $value);
    }
    
    if($ignore) {
        return;
    }

    // Update the setting
    $sql = "UPDATE settings SET value = ? WHERE name = ?";
    $st = $conn->prepare($sql);
    $st->execute([$value, $name]);
}

/**
 * Function to check if a setting exists in the database.
 * @param PDO $conn The database connection.
 * @param string $name The name of the setting.
 * @return bool Whether the setting exists.
 */
function setting_exists($conn, $name) {
    // Check if the setting name exists in the settings table
    $sql = "SELECT COUNT(*) FROM settings WHERE name = ? LIMIT 1";
    $st = $conn->prepare($sql);
    $st->execute([$name]);
    return $st->fetchColumn() > 0;
}

/**
 * Function to create a setting in the database.
 * @param PDO $conn The database connection.
 * @param string $name The name of the setting.
 * @param string $value The value of the setting.
 */
function create_setting($conn, $name, $value) {
    // Create the setting in the settings table
    $sql = "INSERT INTO settings (name, value, type, category, label, tooltip, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $st = $conn->prepare($sql);
    $st->execute([$name, $value, "text", "other", $name, "", ""]);
}