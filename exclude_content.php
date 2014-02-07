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
add_action( 'plugins_loaded', 'exclude_content_init');
function exclude_content_init() {
	$excon = ExcludeContent::instance();
}
register_activation_hook(__FILE__,   array('ExcludeContent', 'on_activation') );
register_deactivation_hook(__FILE__, array('ExcludeContent', 'on_deactivation') );
register_uninstall_hook(__FILE__,    array('ExcludeContent', 'on_uninstall') );



class ExcludeContent {
	
	private static $instance = 0;
	private static $cat_areas = array('home', 'archive', 'search', 'feed', 'other');

	/**
	 * Constructor
	 *
	 * @since	0.1.0
	 */
	public function __construct() {
		// load textdomain
		// load_plugin_textdomain( 'excon', false, 'exclude_content/languages/' );
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_menu', array($this, 'admin_menu'));
		
		add_filter('pre_get_posts', array($this, 'exclude_contents'));
	}
	
	/**
	 * create instance
	 * @return number
	 */
	public static function instance() {
		if ( self::$instance == 0 ) {
			self::$instance = new ExcludeContent();
		}
		return self::$instance;
	}
	
	/**
	 * main function to exclude the Categoreis and Pages
	 * 
	 * @since	0.1.0
	 * 
	 * @param	object	$wp_query
	 */
	public function exclude_contents($wp_query) {
		// don't exclude in backend
		if( is_admin() ) {
			return;
		}
		// if only_main_query is set and the query is NOT the main_query ...
		if( get_option('excon_only_main_query') === 1 && !$wp_query->is_main_query() ) { 
			 return;
		}
		
		// *************** Categories *********************
		// get Options and create exclude Strings
		$cat_settings = get_option('excon_cat_settings');
		$exclude = array();
		foreach(self::$cat_areas as $area) {
			$exclude[$area] = $this->get_exclude_strings($cat_settings[$area]);
		}
		
		// check conditional tags
		if( $wp_query->is_home() ) {
			if( $exclude['home'] ) 		
				$wp_query->set('cat', $exclude['home']);
			
		} elseif ( $wp_query->is_archive() ) {
			if ($exclude['archive'])
				$wp_query->set('cat', $exclude['archive']);
		
		} elseif ( $wp_query->is_search() ) {
			if( $exclude['search'] )
				$wp_query->set('cat', $exclude['search']);
			
		} elseif ( $wp_query->is_feed() ) {
			if( $exclude['feed'] ) 
				$wp_query->set('cat', $exclude['feed']);
			
		} else {
			// gererelles 
			if( $exclude['other'] ) 
				$wp_query->set('cat', $exclude['other']);
		}
	}
	
	
	/**
	 * wandelt ein array in einen sting mit negaiven Vorzeichen um
	 *  
	 * @param	array	$array( 1,2,8 )
	 * @return	string	'-1,-2,-8'
	 */
	private function get_exclude_strings($array) {
		$jarr = array();
		foreach($array as $int) {
			// $jarr[] = $int*-1;
			$jarr[] = '-'.$int;
		}
		return join(',', $jarr);
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
		
		if(!is_array($cat_settings)) {
			// set and update cat settings 
			$cat_settings = array();
			foreach (self::$cat_areas as $area) {
				$cat_settings[$area] = array();
			}
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
		delete_option('excon_only_main_query');
	}

	/**
	 * Registrierung der Settings
	 *
	 * @since	0.1.0
	 */
	public function register_settings() {
		register_setting('exclude_content_settings', 'excon_cat_settings', array($this, 'validate_options_cat'));
		register_setting('exclude_content_settings', 'excon_only_main_query');
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
		// sicherstellen, dass nur das drin ist was wir wollen :-)
		foreach(self::$cat_areas as $area) {
			if(isset($data[$area]) && is_array($data[$area])) {
				foreach($data[$area] as $k => $v) {
					// only INT
					$data[$area][$k] = (int)$v;
				}
			} else {
				// wenn das nicht gestzet ist, setzte es
				$data[$area] = array();
			}
		}
		return $data;
	}
	
	/**
	 * Display Setting Page
	 * 
	 * @since	0.1.0
	 */
	public function display_settings() {
		$cat_settings = get_option('excon_cat_settings');
		$categories   = get_categories(array('hide_empty' => 0,	'order' => 'ASC'));
		$only_main    = get_option('excon_only_main_query');
		?>
<style>
ul.excon {}
ul.excon > li {width: 330px;margin: 0 20px 36px 0;float:left;padding: 10px 0 12px 12px;position: relative;background: #fff;list-style: none;border-radius: 6px;white-space: nowrap;}
ul.excon > li input[type="checkbox"] {display: inline-block;margin: 0 8px 0 0;}
ul.excon > li select {height: 20px;font-size: 11px;text-align: center;background: #f8f8f9;}
ul.excon > li label {cursor: default;display:inline-block;overflow: hidden;line-height: 24px;}
ul.excon > li label span {white-space:normal;width:300px;color: #8e959c;display:block;font-size:12px;line-height:16px;}
</style>
		
		<div class="wrap" id="excon_settings">
			<h2><?php _e('Exclude Content Settings', 'excon'); ?></h2>
			<form method="post" action="options.php">
			<?php settings_fields('exclude_content_settings') ?>
			<h3><?php _e('General Settings', 'excon'); ?></h3>
			<ul class="excon">
				<li>
					<input type="checkbox" name="excon_only_main_query" id="excon_only_main_query" value="1"  <?php checked(get_option('excon_only_main_query')); ?> />
					<label for="excon_only_main_query">Settings nur auf das <strong>main_query</strong> anwenden.  
					<span>Für alle anderen Query (z.B. solche in Templates) finden die Einstellungen keine Anwendung</span></label>
				</li>													
			</ul>
			<div class="clear"></div>
			
			<h3><?php _e('Category Settings', 'excon')?></h3>
			<p>Hier kann ausgewählt werden, welche Kategorie in welchem Bereich nicht angezeigt werden soll / darf.<br />
			   Der Bereich 'Other' wird vermutlich überhaupt nicht gebraucht, da mit den anderen eigentlich schon alle abgedeckt sind ....</p>
			<table class="widefat">
				<thead>
					<tr> 
						<th>ID</th>
						<th><?php _e('Kategorie', 'excon'); ?></th>
						<th><?php _e('FrontPage', 'excon'); ?></th>
						<th><?php _e('Archive', 'excon'); ?></th>
						<th><?php _e('Search', 'excon'); ?></th>
						<th><?php _e('Feed', 'excon'); ?></th>
						<th><?php _e('Other', 'excon'); ?></th>
					</tr>
				</thead>
				<tbody>
		<?php 
		$rows = array();
		$i = 0;
		foreach($categories as $cat) {
			$rows[$i][] = $cat->cat_ID;
			$rows[$i][] = '<strong>'.$cat->cat_name.'</strong> ('.$cat->category_count.')';
			// $rows[$i][] = $cat->category_count;
			foreach (self::$cat_areas as $area) {
				$checked = ( in_array($cat->cat_ID, $cat_settings[$area]) ? ' checked="checked"' : '');
				$rows[$i][] = '<input type="checkbox" name="excon_cat_settings['.$area.'][]" value="'.$cat->cat_ID.'" '.$checked.' /><small>'.$cat->cat_name.'</small>';
			}
			$i++;
		}
		foreach ($rows as $k => $row) {
			$class = (($k % 2) ? ' class="alternate"' : '');
			echo '<tr '.$class.'><td>'.join('</td><td>', $row).'</td></tr>';
		}
		?>
				</tbody>
			</table>
			<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}