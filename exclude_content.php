<?php
/*
Plugin Name: Exclude Content
Description: Mit diesem Plugin können Content-Elemente wie Kategorie, Pages, Posts "versteckt" werden
Author: Ralf Janiszewski
Author URI:
Plugin URI:
Version: 0.1.0
*/

/* Quit */
defined('ABSPATH') OR exit;


/* Hooks */
add_action( 'plugins_loaded', array('ExcludeContent', 'init') );

register_activation_hook(__FILE__,   array('ExcludeContent', 'on_activation') );
register_deactivation_hook(__FILE__, array('ExcludeContent', 'on_deactivation') );
register_uninstall_hook(__FILE__,    array('ExcludeContent', 'on_uninstall') );

class ExcludeContent {
	
	private $cat_areas = array('general', 'home', 'archive', 'search', 'feed');

	/**
	 * Initator der Klasse
	 *
	 * @since	0.1.0
	 */
	public static function init()
	{
		// load textdomain
		load_plugin_textdomain( 'excon', false, 'exclude_content/languages/' );
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_menu', array($this, 'admin_menu'));
		
		add_filter('pre_get_posts','exclude_contents');
		
		
	}
	
	/**
	 * main function to exclude the Categoreis and Pages
	 * 
	 * @since	0.1.0
	 * 
	 * @param	object	$wp_query
	 * @return	object	$wp_query
	 */
	public function exclude_contents($wp_query) {
		
		
		return $wp_query;
	}
	
	
	/**
	 * Add Option Page to AdminMenu
	 * 
	 * @since	0.1.0
	 */
	public function admin_menu() {
		add_options_page( __('Exclude Content Settings', 'excon'), __('Exclude Content', 'excon'), 'manage_options', 'exclude_content.php', array($this, 'display_settings'));
	}
	
	
	/**
	 * activation hook
	 *
	 * @since	0.1.0
	 */
	public static function on_activation() {
		// check cat settings
		$cat_settings = get_option('excon_cat_settings');
		
		$update = false;
		if(is_array($cat_settings)) {
			// check array
			foreach ($this->cat_areas as $area) {
				if(! isset($cat_settings[$area]) ) {
					$cat_settings[$area] = array();
					$update = true;
				}
			}			
		} 
		else {
			$update = true;
			// set and update cat settings 
			$cat_settings = array();
			foreach ($this->cat_areas as $area) {
				$cat_settings[$area] = array();
			}
		}
		if( $update ) {
			update_option('excon_cat_settings', $cat_settings);
		}
	}
	
	/**
	 * deactivation hook
	 *
	 * @since	0.1.0
	 */
	public static function on_deactivation() {}
	
	/**
	 * uninstall hook
	 *
	 * @since	0.1.0
	 */
	public static function on_uninstall() {
		delete_option('excon_cat_settings');
	}

	/**
	 * Registrierung der Settings
	 *
	 * @since	0.1.0
	 */
	public function register_settings() {
		register_setting('exclude_content_settings', 'excon_cat_settings', array($this, 'validate_options_cat'));
	}
	
	/**
	 * Valisierung der Optionsseite für Kategorie
	 *
	 * @since	0.1.0
	 *
	 * @param	array	$data	Array mit Formularwerten
	 * @return  array			Array mit geprüften Werten
	 */
	public function validate_options_cat($data) {
		
		return $data;
	}
	
	/**
	 * Display Setting Page
	 * 
	 * @since	0.1.0
	 */
	public function display_settings() {
		$cat_settings = get_option('excon_cat_settings');
		$categories   = get_categories(array('hide_empty'=>0, 'order'=>'ASC') );
		?>
		<style>
		</style>
		<pre>
		<?php print_r($categories)?>
		</pre>
		
		<div class="wrap" id="excon_settings">
			<h2><?php _e('Exclude Content Settings', 'excon'); ?></h2>
			<form method="post" action="options.php">
			<?php settings_fields('exclude_content_settings') ?>
				<table class="widefat">
					<thead>
						<tr> 
							<th><?php _e('General', 'excon'); ?></th> 
							<th><?php _e('FrontPage', 'excon'); ?></th>
							<th><?php _e('Archive', 'excon'); ?></th>
							<th><?php _e('Search', 'excon'); ?></th>
							<th><?php _e('Feed', 'excon'); ?></th> 
						</tr>
					</thead>
					<tbody>
						<tr><?php 
		foreach($this->cat_areas as $area) {
							?><td>
							<?php
			foreach($categories as $cat) {
				$id      = $cat->cat_ID;
				$name    = $cat->cat_name.' ('.$id.')';
				$html_id = 'exconcatset_'.$area.'_'.$cat->cat_ID;
				$checked = ( in_array($id, $cat_settings[$area]) ? ' checked="checked"' : '');
				
				echo '
							<input type="checkbox" name="excon_cat_settings['.$area.'][]" value="'.$id.'" id="'.$html_id.'" '.$checked.' />'.
						   '<label for="'.$html_id.'">'.$name.'</label> <br />';						
			}
							?></td><?php
		}
						?></tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}