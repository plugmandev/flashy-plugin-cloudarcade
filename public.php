<?php
/**
 * Plugin Name: Flashy
 * Description: A plugin to integrate Flash games to your CloudArcade websites.
 * Author: PlugMan <mail@plugman.dev>
 */

if(!defined('USER_ADMIN')){
    exit;
}

require __dir__ . '/plugman.php';

add_to_hook('footer', function(){
    echo <<<HTML
<style>
/**
 * CloudArcade css fixes
 */

img.small-thumb {
    height: 175px;
    object-fit: cover;
}
</style>
HTML;
});

add_to_hook('footer', function(){
    if(isset($_GET['slug'])):
        $game = Game::getBySlug($_GET['slug']);
        
        if($game):
            if($game->source == "_plugman"):
                try {
                    $plugmanCode = get_setting_value('plugman_flashy_pcode');
                } catch(Exception $e) {
                    $plugmanCode = "";
                }

                try {
                    $plugmanToken = get_setting_value('plugman_flashy_token');
                } catch(Exception $e) {
                    $plugmanToken = "";
                }

                try {
                    $plugmanLogo = get_setting_value('plugman_flashy_logo');
                } catch(Exception $e) {
                    $plugmanLogo = "";
                }

                try {
                    $plugmanBanner = get_setting_value('plugman_flashy_banner');
                } catch(Exception $e) {
                    $plugmanBanner = "";
                }

                $validateToken = plugmanApi("/validate/token", [
                    "token" => $plugmanToken
                ]);

                if($validateToken && $validateToken["status"] == 200){
                    $ownerToken = $validateToken["data"]["token"];
                } else {
                    $getToken = plugmanApi("/generate/token", [
                        "code" => empty($plugmanCode) ? md5(plugman_site_url()) : $plugmanCode,
                        "domain" => plugman_site_url()
                    ]);

                    if($getToken && $getToken["status"] == 200){
                        $ownerToken = $getToken["data"]["token"];
                    } else {
                        $ownerToken = "INVALID_TOKEN";
                    }

                    plugman_update_setting('plugman_flashy_token', $ownerToken);
                }
                
                $isImport = (strpos($game->url, "plugman/flash/import") !== false) ? true : false;
                $gameData = explode('/', rtrim($game->url, '/'));
                $gameType = $isImport ? (isset($gameData[4]) ? $gameData[4] : "Legacy") : "Legacy";
?>
<link rel="stylesheet" href="<?= plugman_site_url("/content/plugins/flashy/assets/css/styles.css"); ?>" />
<script>
    const { fileName, gameType, playerPath, plugmanUrl, playerData } = {
        fileName: "<?php if($isImport){ ?>/game-file.swf?token=<?= $ownerToken; ?>&type=<?= $gameData[4]; ?>&pid=<?php echo $gameData[3]; } ?>",
        gameType: "<?= $gameType; ?>",
        playerPath: addProtocolToURL("<?php if(!$isImport) { echo plugman_site_url("{$game->url}game.swf");  } else { echo $gameType == "GameZip" ? plugman_api . "/game.swf" : plugman_api . "/game-file.swf?token={$ownerToken}&type={$gameData[4]}&pid={$gameData[3]}"; } ?>"),
        plugmanUrl: "<?= plugman_api; ?>",
        playerData: {
            title: "<?= $game->title ?>",
            logo: "<?= plugman_site_url(empty($plugmanLogo) ? "/content/plugins/flashy/assets/images/logo.png" : "/content/plugins/flashy/uploads/{$plugmanLogo}"); ?>",
            banner: "<?= plugman_site_url(empty($plugmanBanner) ? "/content/plugins/flashy/assets/images/banner.png" : "/content/plugins/flashy/uploads/{$plugmanBanner}"); ?>"
        }
    };

    if($("#game-area").length){
        $("#game-area").remove();
        $(".single-icon").remove();
        $(".b-action2").remove();

        $(".game-iframe-container").html(`
            <div class="ruffle-player">
                <div class="preloader">
                    <img src="<?php if($isImport){ echo plugman_api; ?>/game-image.png?pid=<?php echo $gameData[3]; } else { echo plugman_site_url($game->thumb_1); } ?>" class="fullscreenbutton" alt="bgr" />
                    <div class="preloader_title"><?= $game->title ?></div>
                    <button type="button" class="preloader_button">Play Now</button>
                </div>
            </div>
        `);

        $(".game-iframe-container").removeAttr("style");
    }

    function addProtocolToURL(url) {
        if (url.startsWith('//')) {
            return window.location.protocol + url;
        } else {
            return url;
        }
    }
</script>
<script src="<?= plugman_site_url("/content/plugins/flashy/assets/js/jszip.min.js"); ?>"></script>
<script src="<?= plugman_api; ?>/player.js?token=<?= $ownerToken; ?>"></script>
<?php
            endif;
        endif;
    endif;
});