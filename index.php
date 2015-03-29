<?php
/*
* Plugin Name: WordPress Local Profile Picture
* Plugin URI: https://github.com/pontusab/WordPress-Avatar
* Description: Add local avatars to your profile from WordPress-admin. 
* Version: 1.1
* Author: Pontus Abrahamsson & Jonas Skoogh
* Author URI:  http://pontusab.se
* License:     MIT
* License URI: http://www.opensource.org/licenses/mit-license.php
*
* Copyright (c) 2013 Pontus Abrahamsson
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
*/


class WP_avatar
{
	/**
	 * Setup some vars in the object
	 * @since 1.0
	*/ 
	private 
		$upload_path,
		$meta_key,
		$avatar_url,
		$mime_type,
		$formats;


	/**
	 * Name of the form
	 * @since 1.0
	*/ 
	private static $input_field = 'wp_avatar';

	
	/**
	 * Run all the functions and filters on startup
	 * @since 1.0
	*/
	public function __construct()
	{
		// Plugin path
		define( 'WP_AVATAR_URL', plugin_dir_url( __FILE__ ) );

		// Set Object vars
		$this->meta_key    = 'avatar';
		$this->formats     = array( 'jpg', 'jpeg', 'png', 'gif' );
		
		// Url and path to avatar dir
		$this->paths();
		
		// Activation hook
		register_activation_hook( __FILE__, array( &$this, 'activation' ) );

		// Get translations
		load_plugin_textdomain( 'wpa', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

		// Actions 
		add_action( 'admin_init', array( &$this, 'handle_upload' ) );
		add_action( 'admin_menu', array( &$this, 'profile_menu' ) );
		add_filter( 'get_avatar', array( &$this, 'get_avatar'), 10, 5 );
		add_action( 'admin_init', array( &$this, 'scripts') );
	}


	/**
	 * Add path and url to avatar folder
	 * @since 1.0
	*/
	public function paths()
	{
		$upload = wp_upload_dir();
		$this->avatar_url  = $upload['baseurl'] . '/avatars/';
		$this->upload_path = $upload['basedir'] . '/avatars/';
	}


	/**
	 * Add Scripts to Profile-page 
	 * @since 1.0
	*/
	public function scripts() 
	{	
		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		
		// Only add on Upload Avatar Page
		if( $page == 'upload-avatar' )
		{
			wp_register_style( 'style', WP_AVATAR_URL . 'assets/style.css' );
			wp_register_script( 'script', WP_AVATAR_URL . 'assets/script.js' );
			wp_enqueue_style( 'style' );
			wp_enqueue_script( 'script' );
		}
	}


	/**
	 * Add avatars folder on activation 
	 * @since 1.0
	*/
	public function activation()
	{
		if( ! file_exists( $this->upload_path ) ) 
		{
			mkdir( $this->upload_path, 0777 ,true );
		}
	}


	/**
	 * Add Pofile menu to Users
	 * @since 1.0
	*/
	public function profile_menu() 
	{
		add_submenu_page( 
			'profile.php', 
			__('Profile picture', 'wpa'),  
			__('Profile picture', 'wpa'), 
			'read', 
			'upload-avatar', 
			array( &$this, 'avatar_page' ) 
		); 
	}


	/**
	 * Submitform for avatar uplaod
	 * @return Html and form
	 * @since 1.0
	*/
	public function avatar_page()
	{
		$current_user = null;

		if($_POST['wp_avatar_user'])
		{
			$current_user = get_user_by('id', intval($_POST['wp_avatar_user']));
		}
		else 
		{
			$current_user = wp_get_current_user();
		}
		
		$users = get_users();

		$output = '<div class="wrap">';
		$output .= '<div id="icon-users" class="icon32"></div>';
			$output .= '<h2>'. __('Choose user', 'wpa') .'</h2>';

			$output .= '<form method="post">';
				$output .= '<select name="wp_avatar_user">';

					foreach ($users as $user)
					{
						$data = get_user_meta($user->ID, null, true);

						$output .= '<option value="' . $user->ID . '"' . ( $current_user->ID == $user->ID ? ' selected="selected"' : '' ) . '>' . current($data['first_name']) . ' ' . current($data['last_name']) . ' (' . $user->user_email . ')' . '</option>';
					}
					
				$output .= '</select>';
			$output .= '</form>';
		$output .= '</div>';

		$output .= '<div class="wrap">';
			$output .= '<div id="icon-users" class="icon32"></div>';
			$output .= '<h2>'. sprintf( __( 'Change profile picture for %s','wpa'), $current_user->user_email )  .'</h2>';

			$output .= '<div class="avatar-wrap">';

				$output .= get_avatar( $current_user->ID, 200 );

				$output .= '<form method="post" enctype="multipart/form-data">';;
					$output .= '<input type="hidden" name="wp_avatar_user" value="' . $current_user->ID . '">';
					$output .= '<div class="file-upload button">';
						$output .= '<label for="avatar-upload">'. __('Change Profile picture', 'wpa') .'</label>';
						$output .= '<input id="avatar-upload" type="file" name="'. self::$input_field .'" />';
					$output .= '</div>';
					$output .= '<input type="submit" name="save_avatar" value="'. __('Change Profile picture', 'wpa') .'" class="button button-primary">';
				$output .= '</form>';

			$output .= '</div>';
		$output .= '</div>';

		echo $output;
	}


	/**
	 * Takes care of the upload 
	 * @return mime_type and upload file
	 * @since 1.0
	*/
	public function handle_upload()
	{
		if ( count( $_FILES ) > 0 && isset( $_FILES[self::$input_field] ) ) 
		{
			require_once( ABSPATH . '/wp-admin/includes/image.php' );

			// Set the mime_type
			$this->mime_type = $_FILES[self::$input_field]['name'];

			// Save and run the magic on avatars
			$this->save_avatar( $_FILES[self::$input_field]['tmp_name'], 200 );
		}
	}


	/**
	 * Save the avatar to disk and update usermeta
	 * @since 1.0
	*/
	private function save_avatar( $sourcefile, $size )
	{
		$user_id        = intval($_POST['wp_avatar_user']);
		$user           = get_userdata( $user_id );
		$type           = wp_check_filetype( $this->mime_type );
		$image          = wp_get_image_editor( $sourcefile );
		$path_and_name  = $this->upload_path . $user->user_login . '_' . $user->ID . '.';

		// User have avatar but not the same format
		foreach ( $this->formats as $format ) 
		{
			if( file_exists( $path_and_name . $format ) )
			{
				unlink( $path_and_name . $format );
			}
		}

		if ( ! is_wp_error( $image ) ) 
		{
		    $image->resize( $size, $size, true );
		    $image->save( $path_and_name . $type['ext'] );
		
		    // Save the name of the file to user_meta
		    update_user_meta( $user_id, 'avatar', $user->user_login . '_' . $user->ID . '.' . $type['ext'] );
		}
		else 
		{
			$error_string = $image->get_error_message();
   			echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
		}
	}


	/**
	 * Override get_avatar with uploaded avatar else default
	 * @return uploaded avatar else default
	 * @since 1.0
	*/
	public function get_avatar( $avatar, $id_or_email, $size, $default, $alt )
	{	
		if( $id_or_email ) 
		{
			$avatar = get_user_meta( $id_or_email, $this->meta_key, true );

			if( ! empty( $avatar ) )
			{
				$avatar_path = $this->avatar_url . $avatar .'?s='. $size .'';
			}
			else 
			{
				$avatar_path = $default;
			}

			$avatar = "<img alt='{$alt}' src='{$avatar_path}' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";

			return $avatar;
		}
		
		return $avatar;
	}
}

// Initialize the object
$wpa = new WP_avatar;