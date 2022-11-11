<?php
/**
 * Plugin Name:       Chad NYT Best Sellers Plugin
 * Plugin URI:        https://github.com/cbarbee-git/sample-wp-plugin-external-api
 * Description:       This plugin connects to the New York Times via external api and outputs data
 * Text Domain:		  chad-sample-plugin-api
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Chad Barbee
 * Author URI:        https://chadbarbee.com/
 */

defined( 'ABSPATH' ) or die('Direct Access Not Permitted');

add_action('admin_menu','chad_nyt_menu');

function chad_nyt_menu(){
    add_menu_page(
        'NY Times Best Sellers',
        'NY Times Best Sellers',
        'manage_options',
        'chad-sample-plugin-api',
        'run_all_plugin_functions_wrapper',
        'dashicons-book',
        16,
    );
}

function run_all_plugin_functions_wrapper()
{
    //set variables to be used    //might be a better way to store these, maybe the $settings_array below...but for now, here they are.
    $api_option_name = 'nyt_books_info';
    $api_table_version_name = 'nyt_books_table_version';
    $api_table_version_number = '1.0.0';
    //

    //this array can be used to add other settings options later.
    $settings_array = ['nytimes_apikey'];
    RegisterSettings($settings_array,$api_option_name);
    display_plugin_settings($settings_array,$api_option_name);


    //here is where we get data
    if($_POST['sync-data'] == 'true') {
        //Assume API is the first key in this array, set above
        $apiKey = get_option($settings_array[0]);

        //set the params for the API call here
        $params = [
            'offset' => ($_POST['offset']) ? $_POST['offset'] : 20,
            'age_group' => ($_POST['age_group']) ? $_POST['age_group'] : '',
        ];

        //check to see if this option already exists, or need to update
        if (false === get_option($api_option_name)) {
            $action = 'add_option';
        }else{
           $action = 'update_option';
        }
        //call the correct function now. and fetch data to put in the options table
        $action($api_option_name, get_nyt_bestsellers_from_api($apiKey,$params));

        //Create a custom table to hold the data.
        global $wpdb;
        $api_table_name = $wpdb->base_prefix . str_replace('_version', '', $api_table_version_name);
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($api_table_name));
        //check to see if the table exists
        if (!$wpdb->get_var($query) == $api_table_name) {
            //go create it now.
            create_nyt_bestsellers_table(array('api_table_name' => $api_table_name, 'api_table_version_number' => $api_table_version_number, 'api_table_version_name' => $api_table_version_name, 'api_option_name' => $api_option_name));
        }
        //clear the table of existing data,
        $wpdb->prepare("TRUNCATE TABLE %s", $wpdb->esc_like($api_table_name));

        // with options table now set, place data in a table to prevent unnecessary API calles
        // Get the info stored in the database.
        save_nyt_bestsellers_info(array('api_table_name' => $api_table_name, 'api_option_name' => $api_option_name));
    }//end get data
}

function RegisterSettings($settings_array,$api_option_name) {
    foreach ($settings_array as $setting){
        add_option($setting, "", "", "yes");
        register_setting($api_option_name, $setting);
    }
}

function display_plugin_settings($settings_array,$api_option_name){
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php
        if(
                isset( $_GET[ 'page' ] )
                && 'chad-sample-plugin-api' == $_GET[ 'page' ]
                && $_POST
        ) {
            //determine which message to display
            $message = ($_POST['sync-data'] == 'true') ? 'Data has be refreshed.' : 'API Plugin settings saved.';
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?= $message ?></strong>
                </p>
            </div>
            <?php
        }
		?>
        <form id="api-settings" action="" method="post">
            <?php
            // Setup settings section
            add_settings_section(
                'chad-sample-plugin-api_settings_section',
                'Plugin Settings Section',
                '',
                'chad-sample-plugin-api'
            );
            foreach ($settings_array as $setting) {

                //add values
                if (isset($_POST[$setting])) {
                    update_option($setting, $_POST[$setting]);
                }


                // Add text fields
                add_settings_field(
                    $setting . "_input_field",
                    __('New York Times - APIKey', $setting),
                     'input_field_input_field_callback',
                    'chad-sample-plugin-api',
                    'chad-sample-plugin-api_settings_section',
                    $arg = $setting
                );
            }
            // output settings section here
            do_settings_sections('chad-sample-plugin-api');

            //add additional options for the call
            //'offset' is not practical here...but it does show filtering and different data returns
            ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Offset / Where to being results (pagination)</th>
                        <td>
                            <select name="offset" id="offset">
                                <option value="20" <?php echo (isset($_POST['offset']) && $_POST['offset'] == "20") ? "selected " : ""; ?>>20</option>
                                <option value="40" <?php echo (isset($_POST['offset']) && $_POST['offset'] == "40") ? "selected " : ""; ?>>40</option>
                                <option value="60" <?php echo (isset($_POST['offset']) && $_POST['offset'] == "60") ? "selected " : ""; ?>>60</option>
                                <option value="80" <?php echo (isset($_POST['offset']) && $_POST['offset'] == "80") ? "selected " : ""; ?>>80</option>
                                <option value="100" <?php echo (isset($_POST['offset']) && $_POST['offset'] == "1000") ? "selected " : ""; ?>>100</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php
            //These age groups are very arbitrary
            //this is based on the NYT API returns.
            // But this shows how a filter could work....or even a search box.
            // ?>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row">Age Group</th>
                    <td>
                        <select name="age_group" id="age_group">
                            <option value="">Any</option>
                            <option value="Ages 3 to 7" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 3 to 7") ? "selected " : ""; ?>>Ages 3 to 7</option>
                            <option value="Ages 6 to 10" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 6 to 10") ? "selected " : ""; ?>>Ages 6 to 10</option>
                            <option value="Ages 8 to 12" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 8 to 12") ? "selected " : ""; ?>>Ages 8 to 12</option>
                            <option value="Ages 10 and up" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 10 and up") ? "selected " : ""; ?>>Ages 10 and up</option>
                            <option value="Ages 10 to 14" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 10 to 14") ? "selected " : ""; ?>>Ages 10 to 14</option>
                            <option value="Ages 12 and up" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 12 and up") ? "selected " : ""; ?>>Ages 12 and up</option>
                            <option value="Ages 12 to 17" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 12 to 17") ? "selected " : ""; ?>>Ages 12 to 17</option>
                            <option value="Ages 13 and up" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 13 and up") ? "selected " : ""; ?>>Ages 13 and up</option>
                            <option value="Ages 14 and up" <?php echo (isset($_POST['age_group']) && $_POST['age_group'] == "Ages 14 and up") ? "selected " : ""; ?>>Ages 14 and up</option>
                        </select>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php

            // save settings button
            submit_button( 'Save Settings' );                                                                                       //better way to do this...force it here for now.
            submit_button( 'Sync Data',  'primary',  'sync-data-button',false ,  ['id' => 'sync-data-button', 'value' => '' , 'style' => 'background-color:#4CAF50;', 'onMouseOver' => "this.style.backgroundColor='#059862';", 'onMouseOut' => "this.style.backgroundColor='#4CAF50'"] )
            ?>
            <input type="hidden" id="sync-data" name="sync-data" />
            <script>
                jQuery('#sync-data-button').click(function() {
                    jQuery('#sync-data').val('true');
                });
                jQuery('#api-settings').submit();
            </script>
        </form>
    </div>
<?php
}

/**
 * txt field template
 */
function input_field_input_field_callback($arg) {
    if(get_option($arg,'')){
        $plugin_input_field = get_option($arg);
    ?>
    <input type="text" name="<?= $arg ?>" class="regular-text" value="<?php echo isset($plugin_input_field) ? esc_attr( $plugin_input_field ) : ''; ?>" />
    <?php
    }
}

function create_nyt_bestsellers_table ($args){
    global $wpdb;
    $table_name = $args['api_table_name'];
    add_option($args['api_table_version_name'],$args['api_table_version_number']);
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            title text(39),
            bookDescription text(116),
            contributor text(20),
            author text(20),
            price int(20),
            publisher text(20),
            PRIMARY KEY  (id)
        ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

}

function save_nyt_bestsellers_info($args){
    global $wpdb;

    $table_name = $args['api_table_name'];

    $results = json_decode( get_option( $args['api_option_name'] ) )->results;

    foreach( $results as $result ) {

        $wpdb->insert(
            $table_name,
            array(
                'time'            => current_time( 'mysql' ),
                'title'           => $result->title,
                'bookDescription' => is_null($result->description) ? '' : $result->description,
                'contributor'     => is_null($result->contributor) ? '' : $result->contributor,
                'author'          => is_null($result->author) ? '' : $result->author,
                'price'           => is_null($result->price) ? '' : $result->price,
                'publisher'       => is_null($result->publisher) ? '' : $result->publisher,
            )
        );

    }

}

function get_nyt_bestsellers_from_api($apiKey,$params){
    $query_params = [
        'offset' => ($params['offset']) ? $params['offset'] : 20,
        'age_group' => ($params['age_group']) ? $params['age_group'] : '',
    ];
    //TODO: URL is hardcoded
    //this url could be moved somewhere in the DB, in case of any changes....but leave it here for now.
    $url = "https://api.nytimes.com/svc/books/v3/lists/best-sellers/history.json?api-key=$apiKey&" .  http_build_query($query_params);
    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => array(),

    );
    $response = wp_remote_get($url, $args);
    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if(401 === $response_code){
        return 'Unauthorized';
    }
    if(200 !== $response_code){
        return 'Error pinging API';
    }
    if(200 === $response_code){
        return $body;
    }
}