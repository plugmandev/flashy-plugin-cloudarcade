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

if(file_exists(__dir__ . '/info.json')){
    $localInfo = json_decode(file_get_contents(__dir__ . '/info.json'), true);
} else {
    $localInfo = [
        "version" => "0.0.0"
    ];
}

$selected_categories = []; 

if(isset($_SESSION['category'])){
    if(is_array($_SESSION['category'])){
        $selected_categories = (array)$_SESSION['category'];
    } else {
        $selected_categories = commas_to_array($_SESSION['category']);
    }
}

try {
    $pcode = get_setting_value('plugman_flashy_pcode');
} catch(Exception $e) {
    $pcode = "";
}

try {
    $token = get_setting_value('plugman_flashy_token');
} catch(Exception $e) {
    $token = "";
}

try {
    $savefiles = get_setting_value('plugman_flashy_savefiles');
} catch(Exception $e) {
    $savefiles = "";
}

try {
    $lowquality = get_setting_value('plugman_flashy_lowquality');
} catch(Exception $e) {
    $lowquality = "";
}

$validateToken = plugmanApi("/validate/token", [
    "token" => $token
]);

if($validateToken && $validateToken["status"] == 200){
    $ownerToken = $validateToken["data"]["token"];
} else {
    $getToken = plugmanApi("/generate/token", [
        "code" => empty($pcode) ? md5(plugman_site_url()) : $pcode,
        "domain" => plugman_site_url()
    ]);

    if($getToken && $getToken["status"] == 200){
        $ownerToken = $getToken["data"]["token"];
    } else {
        $ownerToken = "INVALID_TOKEN";
    }

    plugman_update_setting('plugman_flashy_token', $ownerToken);
}

?>

<style>
    .game-card {
        border-radius: 10px;
    }

    .game-card .card-img-top {
        height: 175px;
        object-fit: cover;
        background-repeat: no-repeat;
        background-size: cover;
    }

    .game-card.selected {
        background: #333;
        color: #fff;
    }

    .dropdown-menu {
        border-radius: 10px;
    }

    .pagination {
        justify-content: center;
    }

    .card-title, .card-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<div class="container">
    <div class="mb-3 row">
        <!-- Search input -->
        <div class="col-md-4">
            <input type="text" class="form-control mb-2 mb-lg-0" id="searchInput" placeholder="Search...">
        </div>

        <!-- Category filter -->
        <div class="col-md-2">
            <select class="form-select mb-2 mb-lg-0" id="categoryFilter">
                <option value="">All Categories</option>
                <option value="action">Action</option>
                <option value="adventure">Adventure</option>
                <option value="dress-up">Dress-up</option>
                <option value="role-playing">Role-playing</option>
                <option value="puzzle">Puzzle</option>
                <option value="simulation">Simulation</option>
                <option value="escape-the-room">Escape the Room</option>
                <option value="shooter">Shooter</option>
                <option value="platformer">Platformer</option>
                <option value="arcade">Arcade</option>
                <option value="mouse-only">Mouse Only</option>
                <option value="sports">Sports</option>
                <option value="strategy">Strategy</option>
                <option value="educational">Educational</option>
                <option value="point-and-click">Point and Click</option>
            </select>
        </div>

        <!-- Buttons -->
        <div class="col-md-2">
            <button type="button" class="btn btn-primary mb-2 mb-lg-0" id="importAllBtn">
                Import Selected
            </button>
        </div>

        <div class="col-md-2">
            <button type="button" class="btn btn-primary mb-2 mb-lg-0" data-bs-toggle="modal" data-bs-target="#addGameModal">
                Add Flash Game
            </button>
        </div>

        <div class="col-md-2">
            <button type="button" class="btn btn-primary mb-2 mb-lg-0" data-bs-toggle="modal" data-bs-target="#plugmanSettingsModal">
                Settings
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="updateAlert" class="alert alert-warning" role="alert" style="display: none;">
                <i class="fa fa-info-circle" aria-hidden="true"></i> There is a new update for this plugin, please download it from <a href="https://github.com/plugmandev/flashy-plugin-cloudarcade/releases" target="_blank">here</a>.
            </div>

            <div id="importAllLoading" class="alert alert-info" role="alert" style="display: none;">
                <i class="fa fa-sync-alt fa-spin" aria-hidden="true"></i> Importing selected games, please wait...
            </div>

            <div id="importGameLoading" class="alert alert-info" role="alert" style="display: none;">
                <i class="fa fa-sync-alt fa-spin" aria-hidden="true"></i> Importing the game, please wait...
            </div>
            
            <!-- Game Cards -->
            <div id="gameCards" class="row row-cols-1 row-cols-md-5 g-4"></div>
        </div>

        <div class="card-footer">
            <!-- Pagination -->
            <nav id="paginationNav" aria-label="Page navigation example">
                <ul id="paginationList" class="pagination justify-content-center mt-3"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addGameModal" tabindex="-1" aria-labelledby="addGameModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addGameModalLabel">
            Add Flash Game
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form plugman-addgame>
            <input type="hidden" name="source" value="_plugman"/>
            <input type="hidden" name="tags" value=""/>
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label" for="title"><?php _e('Game title') ?>:</label>
                        <input type="text" class="form-control" name="title" value="<?php echo (isset($_SESSION['title'])) ? $_SESSION['title'] : "" ?>" id="game-title-upload" required/>
                    </div>
                    <?php
                    if(CUSTOM_SLUG){ ?>
                        <div class="mb-3">
                            <label class="form-label" for="slug"><?php _e('Game slug') ?>:</label>
                            <input type="text" class="form-control" name="slug" placeholder="game-title" value="<?php echo (isset($_SESSION['slug'])) ? $_SESSION['slug'] : "" ?>" minlength="3" maxlength="50" id="game-slug-upload" required>
                        </div>
                    <?php }
                    ?>
                    <div class="mb-3">
                        <label class="form-label" for="description"><?php _e('Description') ?>:</label>
                        <textarea class="form-control" name="description" rows="3" required><?php echo (isset($_SESSION['description'])) ? $_SESSION['description'] : "" ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="instructions"><?php _e('Instructions') ?>:</label>
                        <textarea class="form-control" name="instructions" rows="3"><?php echo (isset($_SESSION['instructions'])) ? $_SESSION['instructions'] : "" ?></textarea>
                    </div>
                    <label class="form-label" for="gamefile"><?php _e('Game file') ?> (.zip):</label>
                    <ul>
                        <li>Must contain <strong>game.swf</strong> on root</li>
                        <li>Must contain "thumb_1.jpg" (512x384px) on root</li>
                        <li>Must contain "thumb_2.jpg"(512x512px) on root</li>
                    </ul>
                    <div class="input-group mb-3">
                        <div class="custom-file">
                            <label class="form-label" class="custom-file-label" for="input_gamefile"><?php _e('Choose file') ?>:</label>
                            <input type="file" name="gamefile" class="form-control" id="input_gamefile" accept=".zip" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="width"><?php _e('Game width') ?>:</label>
                        <input type="number" class="form-control" name="width" value="<?php echo (isset($_SESSION['width'])) ? $_SESSION['width'] : "720" ?>" required/>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="height"><?php _e('Game height') ?>:</label>
                        <input type="number" class="form-control" name="height" value="<?php echo (isset($_SESSION['height'])) ? $_SESSION['height'] : "1080" ?>" required/>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="category"><?php _e('Category') ?>:</label>
                        <select multiple class="form-control" name="category[]" size="8" required/>
                        <?php
                        $results = array();
                        $data = Category::getList();
                        $categories = $data['results'];
                        foreach ($categories as $cat) {
                            $selected = (in_array($cat->name, $selected_categories)) ? 'selected' : '';
                            echo '<option '.$selected.'>'.$cat->name.'</option>';
                        }
                        ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="tags"><?php _e('Tags') ?>:</label>
                        <input type="text" class="form-control" name="tags" value="<?php echo (isset($_SESSION['tags'])) ? $_SESSION['tags'] : "" ?>" id="tags-upload" placeholder="<?php _e('Separated by comma') ?>">
                    </div>
                    <div class="tag-list">
                        <?php
                        $tag_list = get_tags('usage');
                        if(count($tag_list)){
                            echo '<div class="mb-3">';
                            foreach ($tag_list as $tag_name) {
                                echo '<span class="badge rounded-pill bg-secondary btn-tag" data-target="tags-upload" data-value="'.$tag_name.'">'.$tag_name.'</span>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <?php
                    $extra_fields = get_extra_fields('game');
                    if(count($extra_fields)){
                        ?>
                        <div class="extra-fields">
                            <?php
                            foreach ($extra_fields as $field) {
                                ?>
                                <div class="mb-3">
                                    <label class="form-label" for="<?php echo $field['field_key'] ?>"><?php _e($field['title']) ?>:
                                        <br>
                                        <small class="fst-italic text-secondary"><?php echo $field['field_key'] ?></small>
                                    </label>
                                    <?php
                                    $default_value = $field['default_value'];
                                    $placeholder = $field['placeholder'];
                                    if($field['type'] === 'textarea'){
                                        echo '<textarea class="form-control" name="extra_fields['.$field['field_key'].']" rows="3">'.$default_value.'</textarea>';
                                    } else if($field['type'] === 'number'){
                                        echo '<input type="number" name="extra_fields['.$field['field_key'].']" class="form-control" placeholder="'.$placeholder.'" value="'.$default_value.'">';
                                    } else if($field['type'] === 'text'){
                                        echo '<input type="text" name="extra_fields['.$field['field_key'].']" class="form-control" placeholder="'.$placeholder.'" value="'.$default_value.'">';
                                    }
                                    ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <div class="mb-3">
                <input id="is_mobile" type="checkbox" name="is_mobile" <?php echo (isset($_SESSION['is_mobile']) ? filter_var($_SESSION['is_mobile'], FILTER_VALIDATE_BOOLEAN) : true) ? 'checked' : ''; ?>>
                <label class="form-label" for="is_mobile"><?php _e('Is mobile compatible') ?></label><br>
                <input id="published" type="checkbox" name="published" <?php echo (isset($_SESSION['published']) ? filter_var($_SESSION['published'], FILTER_VALIDATE_BOOLEAN) : true) ? 'checked' : ''; ?>>
                <label class="form-label" for="published"><?php _e('Published') ?></label><br>
                <p style="margin-left: 20px;" class="text-secondary">
                    <?php _e('If unchecked, this game will set as Draft.') ?>
                </p>
            </div>
            <button type="submit" class="btn btn-primary btn-md"><?php _e('Upload game') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="plugmanSettingsModal" tabindex="-1" aria-labelledby="plugmanSettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="plugmanSettingsModalLabel">
            PlugMan Settings
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form plugman-settings>
            <div class="mb-3">
                <label class="form-label">Premium License Code</label>
                <input type="text" class="form-control" name="pcode" placeholder="eg. 101010-10aa-0101-01010-a1b010a01b10" value="<?= $pcode; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">
                    Custom Player Logo
                </label>
                <input type="file" class="form-control" name="logo" accept="image/png">
            </div>
            <div class="mb-3">
                <label class="form-label">
                    Custom Player Banner
                </label>
                <input type="file" class="form-control" name="banner" accept="image/png">
            </div>
            <input type="hidden" name="lowquality" value="0">
            <input type="hidden" name="savefiles" value="0">
            <p class="alert alert-success">
                <small>Flashy is free, but if you want to gain access to thousands of high-quality games, please purchase a premium license: <a href="https://www.buymeacoffee.com/plugman/e/237905" target="_blank">Click here</a></small>
            </p>
            <button type="submit" class="btn btn-primary btn-md">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= plugman_site_url("/content/plugins/flashy/assets/js/plugman.js"); ?>"></script>
<script>
    let selectedGames = {};
    let plugmanSearch = "";
    let plugmanCategory = "";

    $(document).ready(function() {
        // Object to store selected game PIDs
        fetchGamesData();

        // Event listener for import buttons
        $('#gameCards').on('click', '.import-btn', function() {
            var pid = $(this).data('pid');

            $(this).attr('disabled', true);
            $('#importGameLoading').fadeIn();

            $.get(`<?= plugman_api; ?>/import?token=<?= $ownerToken; ?>&pid=${pid}`, function(http) {
                if(http.status == 200) {
                    const gameTitle = http.data.title;

                    $.post(`<?= plugman_site_url("/content/plugins/flashy/controllers/import.php"); ?>`, {
                        source: "_plugman",
                        title: http.data.title,
                        slug: http.data.title,
                        description: http.data.description,
                        instructions: "",
                        thumb_1: `<?= plugman_api; ?>/game-image.png?pid=${http.data.pid}`,
                        thumb_2: `<?= plugman_api; ?>/game-image.png?pid=${http.data.pid}`,
                        url: http.data.gameMeta,
                        width: 1080,
                        height: 1080,
                        "category[]": http.data.category,
                        tags: "",
                        is_mobile: 0,
                        published: 1
                    }, function(http2) {
                        var response = (typeof http2 === "string") ? JSON.parse(http2) : JSON.parse(JSON.stringify(http2));

                        if(response.status === 200) {
                            plugmanAlert("Success!", `Game imported successfully: ${gameTitle}`, "success");
                        } else {
                            plugmanAlert("Attention!", `Something went wrong!`, "danger");
                        }

                        $(`#gameCards .game-card[data-pid="${pid}"]`).removeClass('selected');
                        $(`#gameCards .import-btn[data-pid="${pid}"]`).removeAttr('disabled');
                        $('#importGameLoading').fadeOut();
                    }).fail(function() {
                        plugmanAlert("Attention!", `Failed to import game: ${gameTitle}`, "danger");
                        $(`#gameCards .game-card[data-pid="${pid}"]`).removeClass('selected');
                        $(`#gameCards .import-btn[data-pid="${pid}"]`).removeAttr('disabled');
                        $('#importGameLoading').fadeOut();
                    });
                } else {
                    plugmanAlert("Attention!", `Failed to import game: ${pid}`, "danger");
                    $(`#gameCards .game-card[data-pid="${pid}"]`).removeClass('selected');
                    $(`#gameCards .import-btn[data-pid="${pid}"]`).removeAttr('disabled');
                    $('#importGameLoading').fadeOut();
                }
            }).fail(function() {
                $(`#gameCards .game-card[data-pid="${pid}"]`).removeClass('selected');
                $(`#gameCards .import-btn[data-pid="${pid}"]`).removeAttr('disabled');
                $('#importGameLoading').fadeOut();

                plugmanAlert("Attention!", `Failed to import game: ${pid}`, "danger");
            });
        });

        // Event listener for selecting game cards
        $('#gameCards').on('click', '.game-card', function() {
            var pid = $(this).find('.import-btn').data('pid');
            if (selectedGames[pid]) {
                // Deselect if already selected
                $(this).removeClass('selected');
                delete selectedGames[pid];
            } else {
                // Select if not already selected
                $(this).addClass('selected');
                selectedGames[pid] = true;
            }
        });

        // Event listener for import all button
        $('#importAllBtn').click(function() {
            let selectedPids = Object.keys(selectedGames);
            let doneImports = 0;

            if (selectedPids < 1) {
                plugmanAlert("Attention!", `Please select at least one game to import.`, "danger");
                return;
            }

            // Show loading message
            $('#importAllLoading').show();

            selectedPids.forEach(function(pid) {
                $(`#gameCards .import-btn[data-pid="${pid}"]`).attr('disabled', true);

                $.get(`<?= plugman_api; ?>/import?token=<?= $ownerToken; ?>&pid=${pid}`, function(http) {
                    if(http.status == 200) {
                        const gameTitle = http.data.title;

                        $.post(`<?= plugman_site_url("/content/plugins/flashy/controllers/import.php"); ?>`, {
                            source: "_plugman",
                            title: http.data.title,
                            slug: http.data.title,
                            description: http.data.description,
                            instructions: "",
                            thumb_1: `<?= plugman_api; ?>/game-image.png?pid=${http.data.pid}`,
                            thumb_2: `<?= plugman_api; ?>/game-image.png?pid=${http.data.pid}`,
                            url: http.data.gameMeta,
                            width: 1080,
                            height: 1080,
                            "category[]": http.data.category,
                            tags: "",
                            is_mobile: 0,
                            published: 1
                        }, function(http2) {
                            var response = (typeof http2 === "string") ? JSON.parse(http2) : JSON.parse(JSON.stringify(http2));

                            if(response.status === 200) {
                                plugmanAlert("Success!", `Game imported successfully: ${gameTitle}`, "success");
                            } else {
                                plugmanAlert("Attention!", `Something went wrong!`, "danger");
                            }

                            $(`#gameCards .game-card[data-pid="${pid}"]`).removeClass('selected');
                            $(`#gameCards .import-btn[data-pid="${pid}"]`).removeAttr('disabled');
                            checkImportsCompleted(pid);
                        }).fail(function() {
                            plugmanAlert("Attention!", `Failed to import game: ${gameTitle}`, "danger");
                            checkImportsCompleted(pid);
                        });
                    } else {
                        $(`#gameCards .game-card[data-pid="${pid}"]`).removeClass('selected');
                        $(`#gameCards .import-btn[data-pid="${pid}"]`).removeAttr('disabled');
                        checkImportsCompleted(pid);

                        plugmanAlert("Attention!", `Failed to import game: ${pid}`, "danger");
                    }
                }).fail(function() {
                    plugmanAlert("Attention!", `Failed to import game: ${pid}`, "danger");
                    checkImportsCompleted(pid);
                });
            });

            function checkImportsCompleted(pid) {
                doneImports++;
                if (doneImports === selectedPids.length) {
                    // All games are imported
                    $('#importAllLoading').fadeOut();
                }
                delete selectedGames[pid];
            }
        });

        $("[plugman-addgame]").on("submit", function(e) {
            e.preventDefault();
            
            const plugmanForm = new FormData(this);

            $.ajax({
                url: "<?= plugman_site_url("/content/plugins/flashy/controllers/addgame.php"); ?>",
                type: "POST",
                data: plugmanForm,
                contentType: false,
                processData: false,
                beforeSend: () => {
                    $(`form button, form input, form select`).attr('disabled', true);
                },
                success: (http) => {
                    var response = (typeof http === "string") ? JSON.parse(http) : JSON.parse(JSON.stringify(http));

                    if(response.status === 200) {
                        plugmanAlert("Success!", `Game uploaded successfully!`, "success");
                        $('#addGameModal').modal('hide');
                    } else {
                        plugmanAlert("Attention!", `Something went wrong!`, "danger");
                    }

                    $(`form button, form input, form select`).removeAttr('disabled');
                }
            });
        });

        $("[plugman-settings]").on("submit", function(e) {
            e.preventDefault();
            
            const plugmanForm = new FormData(this);

            $.ajax({
                url: "<?= plugman_site_url("/content/plugins/flashy/controllers/settings.php"); ?>",
                type: "POST",
                data: plugmanForm,
                contentType: false,
                processData: false,
                beforeSend: () => {
                    $(`form button, form input, form select`).attr('disabled', true);
                },
                success: (http) => {
                    var response = (typeof http === "string") ? JSON.parse(http) : JSON.parse(JSON.stringify(http));

                    if(response.status === 200) {
                        plugmanAlert("Success!", response.message, "success");
                        $('#plugmanSettingsModal').modal('hide');
                    } else if(response.status === 302) {
                        plugmanAlert("Success!", response.message, "success");
                        $('#plugmanSettingsModal').modal('hide');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        plugmanAlert("Attention!", response.message, "danger");
                    }

                    $(`form button, form input, form select`).removeAttr('disabled');
                }
            });
        });

        $.get(`https://raw.githubusercontent.com/plugmandev/flashy-plugin-cloudarcade/master/info.json`, function(http) {
            const response = (typeof http === "string") ? JSON.parse(http) : JSON.parse(JSON.stringify(http));

            if(response.version > "<?= $localInfo['version']; ?>"){
                $('#updateAlert').fadeIn();
            }
        });

        // Event listener for search input
        $('#searchInput').on('input', function() {
            plugmanSearch = $(this).val().trim();
            fetchGamesData();
        });

        // Event listener for category filter
        $('#categoryFilter').change(function() {
            plugmanCategory = $(this).val();
            fetchGamesData();
        });
    });

    function fetchGamesData(pageNumber = 1) {
        $("#plugmanNoGames").remove();

        $.get(`<?= plugman_api; ?>/games.json?token=<?= $ownerToken; ?>&limit=20&page=${pageNumber}&search=${plugmanSearch}&category=${plugmanCategory}`, function(response) {
            // Populate game cards
            var gameCardsHtml = '';
            response.games.forEach(function(game) {
                var categorySentenceCase = game.category.toLowerCase().replace(/\b\w/g, function(char) {
                    return char.toUpperCase();
                });

                gameCardsHtml += `
                    <div class="col">
                        <div class="card game-card" data-pid="${game.pid}">
                            <div class="card-img-top" style="background-image: url('<?= plugman_api; ?>/game-image.png?pid=${game.pid}')"></div>
                            <div class="card-body">
                                <h5 class="card-title">${game.title}</h5>
                                <p class="card-text">${categorySentenceCase}</p>
                                <button class="btn btn-primary import-btn" data-pid="${game.pid}">Import</button>
                            </div>
                        </div>
                    </div>
                `;
            });
            $('#gameCards').html(gameCardsHtml);

            if(response.games.length < 1) {
                $('.card-body').prepend(`
                    <div id="plugmanNoGames" class="alert alert-info mb-4" role="alert">
                        <i class="fa fa-info-circle" aria-hidden="true"></i> No games found.
                    </div>
                `);
            }

            // Calculate pagination start and end index
            var totalPages = response.pagination.totalPages;
            var currentPage = response.pagination.currentPage;
            var paginationStart = Math.max(1, currentPage - 5);
            var paginationEnd = Math.min(totalPages, paginationStart + 9);

            // Populate pagination links
            var paginationHtml = '';

            // "First" button
            paginationHtml += `
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="fetchGamesData(1)">First</a>
                </li>
            `;

            // "Previous" button
            paginationHtml += `
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="fetchGamesData(${currentPage - 1})">Previous</a>
                </li>
            `;

            // Page numbers
            for (var i = paginationStart; i <= paginationEnd; i++) {
                paginationHtml += `
                    <li class="page-item ${currentPage === i ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="fetchGamesData(${i})">${i}</a>
                    </li>
                `;
            }

            // "Next" button
            paginationHtml += `
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="fetchGamesData(${currentPage + 1})">Next</a>
                </li>
            `;

            // "Last" button
            paginationHtml += `
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="fetchGamesData(${totalPages})">Last</a>
                </li>
            `;

            $('#paginationList').html(paginationHtml);

            Object.keys(selectedGames).forEach(function(pid) {
                $(`#gameCards .game-card[data-pid="${pid}"]`).addClass('selected');
            });
        });
    }
</script>

