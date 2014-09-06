<?php

/*
  Plugin Name: thePlatform Video Manager
  Plugin URI: http://theplatform.com/
  Description: Manage video assets hosted in thePlatform MPX from within WordPress.
  Version: 1.2.2
  Author: thePlatform for Media, Inc.
  Author URI: http://theplatform.com/
  License: GPL2

  Copyright 2013-2014 thePlatform for Media, Inc.

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This is thePlatform's plugin entry class, all initalization and AJAX handlers are defined here.
 */
class ThePlatform_Plugin {

	private $plugin_base_dir;
	private $plugin_base_url;
	private static $instance;

	/**
	 * Creates one instance of the plugin
	 * @return ThePlatform_Plugin New or existing instance of ThePlatform_Plugin
	 */
	public static function init() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new ThePlatform_Plugin;
		}

		return self::$instance;
	}

	function __construct() {
		require_once(dirname( __FILE__ ) . '/thePlatform-constants.php');
		require_once(dirname( __FILE__ ) . '/thePlatform-URLs.php');
		require_once(dirname( __FILE__ ) . '/thePlatform-API.php');
		require_once(dirname( __FILE__ ) . '/thePlatform-helper.php');
		require_once(dirname( __FILE__ ) . '/thePlatform-proxy.php');
				
		$this->tp_api = new ThePlatform_API;
		
		//Disable oLark in if the plugin is loaded in AJAX
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_filter( 'vip_live_chat_enabled', '__return_false' );
		}

		$this->plugin_base_dir = plugin_dir_path( __FILE__ );
		$this->plugin_base_url = plugins_url( '/', __FILE__ );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
			add_action( 'admin_init', array( $this, 'register_scripts' ) );
			add_action( 'wp_ajax_initialize_media_upload', array( $this->tp_api, 'initialize_media_upload' ) );			
			add_action( 'wp_ajax_theplatform_media', array( $this, 'embed' ) );
			add_action( 'wp_ajax_theplatform_upload', array( $this, 'upload' ) );
			add_action( 'wp_ajax_theplatform_edit', array( $this, 'edit' ) );
			add_action( 'wp_ajax_get_categories', array( $this->tp_api, 'get_categories' ) );
			add_action( 'wp_ajax_get_videos', array( $this->tp_api, 'get_videos' ) );
			add_action( 'wp_ajax_set_thumbnail', array( $this, 'set_thumbnail_ajax' ) );
		}
		add_shortcode( 'theplatform', array( $this, 'shortcode' ) );
	}

	/**
	 * Registers javascripts and css used throughout the plugin	 
	 */
	function register_scripts() {
		wp_register_script( 'pdk', "//pdk.theplatform.com/pdk/tpPdk.js" );
		wp_register_script( 'holder', plugins_url( '/js/holder.js', __FILE__ ) );
		wp_register_script( 'handlebars', plugins_url( '/js/handlebars-v1.3.0.js', __FILE__ ) );
		wp_register_script( 'bootstrap_js', plugins_url( '/js/bootstrap.min.js', __FILE__ ), array( 'jquery' ) );
		wp_register_script( 'theplatform_js', plugins_url( '/js/theplatform.js', __FILE__ ), array( 'jquery' ) );
		wp_register_script( 'infiniscroll_js', plugins_url( '/js/jquery.infinitescroll.min.js', __FILE__ ), array( 'jquery' ) );
		wp_register_script( 'mpxhelper_js', plugins_url( '/js/mpxHelper.js', __FILE__ ), array( 'jquery' ) );
		wp_register_script( 'theplatform_uploader_js', plugins_url( '/js/theplatform-uploader.js', __FILE__ ), array( 'jquery', 'theplatform_js' ) );
		wp_register_script( 'mediaview_js', plugins_url( '/js/mediaview.js', __FILE__ ), array( 'jquery', 'jquery-ui-dialog', 'handlebars', 'holder', 'mpxhelper_js', 'theplatform_js', 'pdk', 'infiniscroll_js', 'bootstrap_js' ) );
		wp_register_script( 'field_views', plugins_url( '/js/fieldViews.js', __FILE__ ), array( 'jquery' ) );

		wp_localize_script( 'theplatform_js', 'theplatform_local', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'plugin_base_url' => plugins_url( 'images/', __FILE__ ),
			'tp_nonce' => array( 				
				'verify_account' => wp_create_nonce( 'theplatform-ajax-nonce-verify_account' ),
				'theplatform_edit' => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_edit' ),
				'theplatform_media' => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_media' ),
				'theplatform_upload' => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_upload' )
			)
		) );		

		wp_localize_script( 'theplatform_uploader_js', 'theplatform_uploader_local', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),			
			'tp_nonce' => array( 				
				'initialize_media_upload' => wp_create_nonce( 'theplatform-ajax-nonce-initialize_media_upload' ),
				'publishMedia' => wp_create_nonce( 'theplatform-ajax-nonce-publishMedia' ),
				'uploadStatus' => wp_create_nonce( 'theplatform-ajax-nonce-uploadStatus' ),
				'cancelUpload' => wp_create_nonce( 'theplatform-ajax-nonce-cancelUpload' ),
				'startUpload' => wp_create_nonce( 'theplatform-ajax-nonce-startUpload' ),
			)
		) );	

		wp_localize_script( 'mpxhelper_js', 'mpxhelper_local', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),			
			'tp_nonce' => array( 				
				'get_videos' => wp_create_nonce( 'theplatform-ajax-nonce-get_videos' ),
				'get_categories' => wp_create_nonce( 'theplatform-ajax-nonce-get_categories' )
			)
		) );	

		wp_localize_script( 'mediaview_js', 'mediaview_local', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),			
			'tp_nonce' => array( 				
				'set_thumbnail' => wp_create_nonce( 'theplatform-ajax-nonce-set_thumbnail' )
			)
		) );		

		wp_register_style( 'theplatform_css', plugins_url( '/css/thePlatform.css', __FILE__ ) );
		wp_register_style( 'bootstrap_tp_css', plugins_url( '/css/bootstrap_tp.min.css', __FILE__ ) );
		wp_register_style( 'field_views', plugins_url( '/css/fieldViews.css', __FILE__ ) );
	}

	/**
	 * Add admin pages to Wordpress sidebar
	 */
	function add_admin_page() {
		$tp_admin_cap = apply_filters( TP_ADMIN_CAP, TP_ADMIN_DEFAULT_CAP );
		$tp_viewer_cap = apply_filters( TP_VIEWER_CAP, TP_VIEWER_DEFAULT_CAP );
		$tp_uploader_cap = apply_filters( TP_UPLOADER_CAP, TP_UPLOADER_DEFAULT_CAP );
		$slug = 'theplatform';
		add_menu_page( 'thePlatform', 'thePlatform', $tp_viewer_cap, $slug, array( $this, 'media_page' ), 'dashicons-video-alt3', '10.0912' );
		add_submenu_page( $slug, 'thePlatform Video Browser', 'Browse MPX Media', $tp_viewer_cap, $slug, array( $this, 'media_page' ) );
		add_submenu_page( $slug, 'thePlatform Video Uploader', 'Upload Media to MPX', $tp_uploader_cap, 'theplatform-uploader', array( $this, 'upload_page' ) );
		add_submenu_page( $slug, 'thePlatform Plugin Settings', 'Settings', $tp_admin_cap, 'theplatform-settings', array( $this, 'admin_page' ) );
		add_submenu_page( $slug, 'thePlatform Plugin About', 'About', $tp_admin_cap, 'theplatform-about', array( $this, 'about_page' ) );
	}

	/**
	 * Calls the plugin's options page template	 
	 */
	function admin_page() {
		require_once(dirname( __FILE__ ) . '/thePlatform-options.php' );
	}

	/**
	 * Calls the Media Manager template
	 */
	function media_page() {
		require_once( dirname( __FILE__ ) . '/thePlatform-media.php' );
	}

	/**
	 * Calls the Upload form template
	 */
	function upload_page() {
		require_once( dirname( __FILE__ ) . '/thePlatform-upload.php' );
	}
	
	/**
	 * Calls the About page template
	 */
	function about_page() {
		require_once( dirname( __FILE__ ) . '/thePlatform-about.php' );
	}

	/**
	 * Calls the Embed template in an IFrame and Dialog
	 */
	function embed() {
		check_admin_referer( 'theplatform-ajax-nonce-theplatform_media' );
		
		$tp_embedder_cap = apply_filters( TP_EMBEDDER_CAP, TP_EMBEDDER_DEFAULT_CAP );
		if ( !current_user_can( $tp_embedder_cap ) ) {
			wp_die( 'You do not have sufficient permissions to embed videos' );
		}
		
		require_once( $this->plugin_base_dir . 'thePlatform-media-browser.php' );
		die();
	}

	/**
	 * Calls the Embed template in an IFrame and Dialog
	 */
	function edit() {
		check_admin_referer( 'theplatform-ajax-nonce-theplatform_edit' );
		
		$tp_uploader_cap = apply_filters( TP_UPLOADER_CAP, TP_UPLOADER_DEFAULT_CAP );
		if ( !current_user_can( $tp_uploader_cap ) ) {
			wp_die( 'You do not have sufficient permissions to edit videos' );
		}
		
		$args = array( 'fields' => $_POST['params'], 'custom_fields' => $_POST['custom_params'] );
		$this->tp_api->update_media( $args );
	}

	/**
	 * Calls the Upload Window template in a popup
	 */
	function upload() {
		check_admin_referer( 'theplatform-ajax-nonce-theplatform_upload' );
		
		$tp_uploader_cap = apply_filters( TP_UPLOADER_CAP, TP_UPLOADER_DEFAULT_CAP );
		if ( !current_user_can( $tp_uploader_cap ) ) {
			wp_die( 'You do not have sufficient permissions to upload videos' );
		}
		
		require_once( $this->plugin_base_dir . 'thePlatform-upload-window.php' );
		die();
	}

	/**
	 * Ajax callback to initiate the change of a Post default thumbnail
	 * @return string HTML code to update the Post page to display the new thumbnail
	 */
	function set_thumbnail_ajax() {
		check_admin_referer( 'theplatform-ajax-nonce-set_thumbnail' );
		
		$tp_embedder_cap = apply_filters( TP_EMBEDDER_CAP, TP_EMBEDDER_DEFAULT_CAP );
		if ( !current_user_can( $tp_embedder_cap ) ) {
			wp_die( 'You do not have sufficient permissions to change the post thumbnail' );
		}

		global $post_ID;

		if ( !isset( $_POST['id'] ) ) {
			wp_send_json_error( "Post ID not found" );
		}

		$post_ID = intval( $_POST['id'] );

		if ( !$post_ID ) {
			wp_send_json_error( "Illegal Post ID" );
		}

		$url = isset( $_POST['img'] ) ? $_POST['img'] : '';

		$url = esc_url_raw( $url );

		$thumbnail_id = $this->set_thumbnail( $url, $post_ID );

		if ( $thumbnail_id !== FALSE ) {
			set_post_thumbnail( $post_ID, $thumbnail_id );
			wp_send_json_success( _wp_post_thumbnail_html( $thumbnail_id, $post_ID ) );
		}

		//TODO: Better error
		wp_send_json_error( "Something went wrong" );
	}

	/**
	 * Change the provided Post ID default thumbnail
	 * @param string $url  Link to the image URL
	 * @param int $post_id WordPress Post ID to apply the change to
	 * @return int The newly created WordPress Thumbnail ID
	 */
	function set_thumbnail( $url, $post_id ) {
		$file = download_url( $url );

		preg_match( '/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches );
		$file_array['name'] = basename( $matches[0] );
		$file_array['tmp_name'] = $file;

		if ( is_wp_error( $file ) ) {
			unlink( $file_array['tmp_name'] );
			return false;
		}

		$thumbnail_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $thumbnail_id ) ) {
			unlink( $file_array['tmp_name'] );
			return false;
		}

		return $thumbnail_id;
	}

	/**
	 * Shortcode Callback
	 * @param array $atts Shortcode attributes
	 * @return string thePlatform video embed shortcode
	 */
	function shortcode( $atts ) {
		if ( !class_exists( 'ThePlatform_API' ) ) {
			require_once( dirname( __FILE__ ) . '/thePlatform-API.php' );
		}

		if ( !isset( $this->preferences ) ) {
			$this->preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
		}
		
		if ( !isset( $this->account ) ) {
			$this->account = get_option( TP_ACCOUNT_OPTIONS_KEY );
		}

		list( $account, $width, $height, $media, $player, $mute, $autoplay, $loop, $tag, $embedded, $params ) = array_values( shortcode_atts( array(
			'account' => '',
			'width' => '',
			'height' => '',
			'media' => '',
			'player' => '',
			'mute' => '',
			'autoplay' => '',
			'loop' => '',
			'tag' => '',
			'embedded' => '',
			'params' => '' ), $atts
				)
		);
				
		if ( empty( $width ) ) {
			$width = (int) $this->preferences['default_width'];
		}
		if ( strval( $width ) === '0' ) {
			$width = 500;
		}		

		if ( empty( $height ) ) {
			$height = $this->preferences['default_height'];
		}
		if ( strval( $height ) === '0' ) {
			$height = floor( $width * 9 / 16 );
		}

		$mute = $this->check_shortcode_parameter( $mute, 'false', array( 'true', 'false' ) );
		$loop = $this->check_shortcode_parameter( $loop, 'false', array( 'true', 'false' ) );
		$autoplay = $this->check_shortcode_parameter( $autoplay, $this->preferences['autoplay'], array( 'false', 'true' ) );
		$embedded = $this->check_shortcode_parameter( $embedded, $this->preferences['player_embed_type'], array( 'true', 'false' ) );		
		$tag = $this->check_shortcode_parameter( $tag, $this->preferences['embed_tag_type'], array( 'iframe', 'script' ) );

		if ( empty( $media ) ) {
			return '<!--Syntax Error: Required Media parameter missing. -->';
		}

		if ( empty( $player ) ) {
			return '<!--Syntax Error: Required Player parameter missing. -->';
		}
		
		if ( empty ( $account ) ) {
			$account = $this->account['mpx_account_pid'];
		}
		
		
		if ( !is_feed() ) {			
			$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, $tag, $embedded, $loop, $mute, $params );
			$output = apply_filters( 'tp_embed_code', $output );
		} else {
			switch ( $this->preferences['rss_embed_type'] ) {			
				case 'article':
					$output = '[Sorry. This video cannot be displayed in this feed. <a href="' . get_permalink() . '">View your video here.]</a>';
					break;
				case 'iframe':						
					$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, 'iframe', $embedded, $loop, $mute, $params );
					break;
				case 'script':
					$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, 'script', $embedded, $loop, $mute, $params );
					break;
				default:
					$output = '[Sorry. This video cannot be displayed in this feed. <a href="' . get_permalink() . '">View your video here.]</a>';
					break;
			}
			$output = apply_filters( 'tp_rss_embed_code', $output );
		}

		return $output;
	}
	
	/**
	 * Checks a shortcode value is valid and if not returns a default value
	 * @param string $value The shortcode parameter value
	 * @param string $defaultValue The default value to return if a user entered an invalid entry.
	 * @param array $allowedValues An array of valid values for the shortcode parameter
	 * @return string The final value
	 */
	function check_shortcode_parameter( $value, $defaultValue, $allowedValues ) {
		
		$value = strtolower( $value );
		
		if ( empty ( $value ) ) {
			return $defaultValue;							
		} else if ( array_search( $value, $allowedValues) === 0 ) {
			return $value;
		}
				
		if ( !empty ( $defaultValue ) ) {
			return $defaultValue;
		}
		
		return $allowedValues[0];
	}

	/**
	 * Called by the plugin shortcode callback function to construct a media embed iframe.
	 *
	 * @param string $accountPID Account of the user embedding the media asset
	 * @param string $releasePID Identifier of the media object to embed
	 * @param string $playerPID Identifier of the player to display the embedded media asset in
	 * @param string $player_width The width of the embedded player
	 * @param string $player_height The height of the embedded player
	 * @param boolean $autoplay Whether or not to loop the embedded media automatically
	 * @param boolean $tag script or iframe embed tag style
	 * @param boolean $loop Set the embedded media to loop, false by default
	 * @param boolean $mute Whether or not to mute the audio channel of the embedded media asset, false by default
	 * @param string $params Any additional parameters to add to the embed code
	 * @return string An iframe tag sourced from the selected media embed URL
	 */
	function get_embed_shortcode( $accountPID, $releasePID, $playerPID, $player_width, $player_height, $autoplay, $tag, $embedded, $loop = false, $mute = false, $params = '' ) {

		$url = TP_API_PLAYER_EMBED_BASE_URL . urlencode( $accountPID ) . '/' . urlencode( $playerPID );
		
		if ( $embedded === 'true') {
			$url .= '/embed';
		}
		
		$url .= '/select/' . urlencode( $releasePID );

		$url = apply_filters( 'tp_base_embed_url', $url );

		$url .= '?width=' . (int) $player_width . '&height=' . (int) $player_height;

		if ( $loop !== "false" ) {
			$url .= "&loop=true";
		}

		if ( $autoplay !== "false" ) {
			$url .= "&autoPlay=true";
		}

		if ( $mute !== "false" ) {
			$url .= "&mute=true";
		}

		if ( $params !== '' ) {
			$url .= '&' . $params;
		}

		$url = apply_filters( 'tp_full_embed_url', $url );

		if ( $tag == "script" ) {
			return '<div style="width:' . esc_attr( $player_width ) . 'px; height:' . esc_attr( $player_height ) . 'px"><script type="text/javascript" src="' . esc_url_raw( $url . "&form=javascript" ) . '"></script></div>';
		} else { //Assume iframe			
			return '<iframe src="' . esc_url( $url ) . '" height=' . esc_attr( $player_height ) . ' width=' . esc_attr( $player_width ) . ' frameBorder="0" seamless="seamless" allowFullScreen></iframe>';
		}
	}

}

// Instantiate thePlatform plugin on WordPress init
add_action( 'init', array( 'ThePlatform_Plugin', 'init' ) );
add_action( 'wp_ajax_verify_account', 'theplatform_verify_account_settings' );
add_action( 'admin_init', 'theplatform_register_plugin_settings' );
add_action( 'admin_init', 'theplatform_check_plugin_update');
add_action( 'init', 'theplatform_buttonhooks' );

/**
 * TinyMCE filter hooks to add a new button
 */
function theplatform_buttonhooks() {
	$tp_embedder_cap = apply_filters( TP_EMBEDDER_CAP, TP_EMBEDDER_DEFAULT_CAP );
	if ( current_user_can( $tp_embedder_cap ) ) {
		add_filter( "mce_external_plugins", "theplatform_register_tinymce_javascript" );
		add_filter( 'mce_buttons', 'theplatform_register_buttons' );
		add_filter('tiny_mce_before_init','theplatform_tinymce_settings');
	}
}

/**
 * Register a new button in TinyMCE
 */
function theplatform_register_buttons( $buttons ) {
	array_push( $buttons, "|", "theplatform" );
	return $buttons;
}

/**
 * Load the TinyMCE plugin
 * @param  array $plugin_array Array of TinyMCE Plugins
 * @return array The array of TinyMCE plugins with our plugin added
 */
function theplatform_register_tinymce_javascript( $plugin_array ) {
	$plugin_array['theplatform'] = plugins_url( '/js/theplatform.tinymce.plugin.js?matan', __file__ );
	return $plugin_array;
}

/**
 * Registers initial plugin settings during initalization
 */
function theplatform_register_plugin_settings() {
	register_setting( TP_ACCOUNT_OPTIONS_KEY, TP_ACCOUNT_OPTIONS_KEY, 'theplatform_account_options_validate' );	
	register_setting( TP_PREFERENCES_OPTIONS_KEY, TP_PREFERENCES_OPTIONS_KEY, 'theplatform_preferences_options_validate' );
	register_setting( TP_METADATA_OPTIONS_KEY, TP_METADATA_OPTIONS_KEY, 'theplatform_dropdown_options_validate' );
	register_setting( TP_UPLOAD_OPTIONS_KEY, TP_UPLOAD_OPTIONS_KEY, 'theplatform_dropdown_options_validate' );
}

/**
 * Add our nonce to tinymce so we can call our templates
 * @param  array $settings tinyMCE settings
 * @return array The array of tinyMCE settings with our value added
 */
function theplatform_tinymce_settings($settings)
{
    $settings['theplatform_media_nonce'] = wp_create_nonce( 'theplatform-ajax-nonce-theplatform_media' );

    return $settings;
}

/**
 * Checks if the plugin has been updated and performs any necessary updates.
 */
function theplatform_check_plugin_update() {
	if ( !theplatform_plugin_version_changed() ) {
		return;
	}
	
	// Move account settings from preferences (1.2.0)
	$properties = get_option( TP_PREFERENCES_OPTIONS_KEY, array() );
	if ( array_key_exists( 'mpx_account_id', $properties ) ) {
		$accountSettings = TP_ACCOUNT_OPTIONS_DEFAULTS();
		foreach ( $properties as $key => $value ) {
			if ( array_key_exists( $key, $accountSettings ) ) {
				$accountSettings[$key] = $properties[$key];				
			}
		}
		update_option( TP_ACCOUNT_OPTIONS_KEY, $accountSettings );
	}	
}