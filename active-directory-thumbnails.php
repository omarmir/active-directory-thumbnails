<?php/*Plugin Name: Active Directory ThumbnailsPlugin URI: http://infinity88.caDescription: This plugin takes active directory thumbnail octet strings and converts them to jpeg files stored on the server.Version: 1.01Author: Omar MirAuthor URI: http://infinity88.caLicense: GPL3*/include(sprintf("%s/lib/helper.php", dirname(__FILE__)));if (!class_exists('adt_i88')) {    class adt_i88 {        private static $helper;        public function __construct() {            self::$helper = new adt_i88_helper();            add_action('admin_menu', array($this, 'add_menu'));            add_action('init', array($this, 'adt_register'));            add_action('init', array($this, 'adt_i88_endpoint'));            add_action( 'parse_query', array($this, 'adt_i88_parse_query' ));        }        public static function activate() {            /* Soft flush rewrite rules */            flush_rewrite_rules(false);            /* Set initial nonce */            $adt_nonce = wp_create_nonce(rand());            /* Database field and upload folder creation */            add_option('adt_ad_thumbnail', 'adi_thumbnailphoto', '', 'no');            add_option('adt_replace_avatar', '', '', 'yes');            add_option('adt_nonce', $adt_nonce, '', 'no');            mkdir(wp_upload_dir()['basedir'] . '/active-directory-thumbnails', 0755);        }        public function adt_i88_endpoint(){            /*  We will use this for the bulk process and the individual picture generation process */            add_rewrite_endpoint( 'active_directory_thumbnails', EP_ROOT );        }        public function adt_i88_parse_query( $query ){            if( isset( $query->query_vars['active_directory_thumbnails'] ) ){                if ( $_REQUEST['nonce'] != get_option('adt_nonce')) {                    exit("No naughty business please"); //ONLY DO THIS IF YOU ARE IN THE ENDPOINT, otherwise it just stops all queries withing the nonce                }                if (empty($query->query_vars['active_directory_thumbnails'])) { //If no ID is present then do the bulk                    self::$helper->adt_bulk_get_users_and_save_pics();                    exit;                } else { //If ID is present then do the individual process                    self::$helper->adt_get_user_photo($query->query_vars['active_directory_thumbnails']);                    exit;                }            }        }        public static function deactivate() {            /* Redo rewrite rules to remove endpoint */            flush_rewrite_rules(false);            /* Deletes the database field */            delete_option('adt_ad_thumbnail');            delete_option('adt_replace_avatar');            delete_option('adt_nonce');            /* Delete thumbnail folder */            self::$helper->adt_delete_folder(wp_upload_dir()['basedir'] . '/active-directory-thumbnails');            /* Deletes all database links to generated thumbs */            $aUsersID = self::$helper->adt_get_all_users();            foreach($aUsersID as $iUserID):                delete_user_meta($iUserID, 'adt_user_photo_url');                delete_user_meta($iUserID, 'adt_user_photo_filename');            endforeach; // end the users loop.        }        public function add_menu() {            add_users_page('Active Directory Thumbnails Options', 'Active Directory Thumbnails', 1, 'active-directory-thumbnails', array(&$this, 'plugin_settings_page'));        }        public function plugin_settings_page() {            if(!current_user_can('manage_options')) {                wp_die(__('You do not have sufficient permissions to access this page.'));            }            self::adt_enqueue();            include(sprintf("%s/lib/options.php", dirname(__FILE__)));        }        public function adt_register() {            wp_register_style( 'jquery-ui-theme', WP_PLUGIN_URL.'/active-directory-thumbnails/css/jquery-ui.css' );            wp_register_script( 'adt', plugins_url( '/js/adt.js', __FILE__ ), array('jquery'));        }        public function adt_enqueue() {            wp_enqueue_script( 'jquery-ui-core' );            wp_enqueue_script( 'jquery-ui-progressbar' );            wp_enqueue_style( 'jquery-ui-theme' );            //Get strings ready to provide to the JavaScript Localize API in WordPress            $alert_msg = __('Please do not close the browser while this process is ongoing. Please press OK to start.', 'active-directory-thumbnails');            $completed_msg = __('Done! Created image for', 'active-directory-thumbnails');            wp_localize_script( 'adt', 'adt_url_users', array(                'alert_msg'     => $alert_msg,                'completed_msg' => $completed_msg,                'adt_nonce'     => get_option('adt_nonce'),                'site_url'      => site_url(), //Also using the Translate API to pass some data we will need.                'user_list'     => self::$helper->adt_get_all_users() //See above comment                ) );            wp_enqueue_script( 'adt' );        }    }}/* Runs when plugin is activated */register_activation_hook(__FILE__, array('adt_i88', 'activate'));/* Runs on plugin deactivation*/register_deactivation_hook(__FILE__, array('adt_i88', 'deactivate'));$wp_plugin_template = new adt_i88();// Add a link to the settings page onto the plugin pageif(isset($wp_plugin_template)) {    // Add the settings link to the plugins page    function adt_i88_plugin_settings_link($links) {        $settings_translate = __('Settings', 'active-directory-thumbnails');        $settings_link = '<a href="users.php?page=active-directory-thumbnails">' . $settings_translate . '</a>';        array_unshift($links, $settings_link);        return $links;    }    $plugin = plugin_basename(__FILE__);    add_filter("plugin_action_links_$plugin", 'adt_i88_plugin_settings_link');}if (get_option('adt_replace_avatar', false)) {    add_filter( 'get_avatar' , 'adt_replace_avatar' , 20 , 5 );}function adt_replace_avatar( $avatar, $id_or_email, $size, $default, $alt ) {    $user = false;    if ( is_numeric( $id_or_email ) ) { //Is it the ID?        $id = (int) $id_or_email;        $user = get_user_by( 'id' , $id );    } elseif ( is_object( $id_or_email ) ) { //Did someone pass the user object!?        if ( ! empty( $id_or_email->user_id ) ) {            $id = (int) $id_or_email->user_id;            $user = get_user_by( 'id' , $id );        }    } else { //Is it the email?        $user = get_user_by( 'email', $id_or_email );    }    if (isset($user->adt_user_photo_url)) {        $file_url = wp_upload_dir()['baseurl'] . '/active-directory-thumbnails/' . $user->adt_user_photo_filename;        $avatar = "<img alt='{$alt}' src='{$file_url}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";    } else {        return $avatar;    }    return $avatar;}