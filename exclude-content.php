<?php
/*
Plugin Name: Exclude Content
Description: Mit diesem Plugin können Content Kategorie "versteckt" werden
Author: Ralf Janiszewski
Author URI:
Plugin URI:
Version: 0.3.1
*/

/* Quit */
defined('ABSPATH') OR exit;


/* Hooks */
add_action( 'plugins_loaded', 'exclude_content_init');

register_activation_hook(__FILE__,   array('ExcludeContent', 'on_activation') );
register_deactivation_hook(__FILE__, array('ExcludeContent', 'on_deactivation') );
register_uninstall_hook(__FILE__,    array('ExcludeContent', 'on_uninstall') );


function exclude_content_init() {
	$excon = ExcludeContent::instance();
}




class ExcludeContent {
	
	private static $instance = 0;
	private static $cat_areas = array('home', 'archive', 'search', 'feed', 'other');

	/**
	 * Constructor
	 *
	 * @since	0.2.0
	 */
	public function __construct() {
		// load textdomain
		// load_plugin_textdomain( 'excon', false, 'exclude-content/languages/' );
		
		if( is_admin() ) {
			// .... Tiggers Hook on the Backend
			add_action('admin_init', array($this, 'register_settings'));
			add_action('admin_menu', array($this, 'admin_menu'));
			
			// OK, das machen wir nur wenn erwünscht...
			// Upps tut nicht (Warum tut es nicht ????) 
			if( get_option('excon_posts_excludes_enable') ) {
				// Update checkboxes after Edit page changes
				add_action('save_post', array($this, 'update_page_excludes'), 10, 1);
				// Add checkboxes to page 'post_submitbox_start'
				add_action('post_submitbox_misc_actions', array($this, 'add_page_checkbox'));
			}
		} else {
			// .... Tiggers Hook on the FrontEnd
			add_filter('pre_get_posts', array($this, 'exclude_contents'));
		}
	}
	
	/**
	 * create instance
	 * 
	 * @since	0.2.0
	 * 
	 * @return number
	 */
	public static function instance() {
		if ( self::$instance == 0 ) {
			self::$instance = new ExcludeContent();
		}
		return self::$instance;
	}
	
	/**
	 * get array id-list 
	 * 
	 * @since 0.2.1
	 * @return array (always)
	 */
	private function get_exclude_posts_ids() {
		$excon_posts = get_option('excon_posts_excludes');
		if( !is_array($excon_posts) ) {
			return array();
		}
		return $excon_posts;
	}
	
	/**
	 * main function to exclude the Categoreis and Pages
	 * 
	 * @since	0.1.0
	 * 
	 * @param	object	$wp_query
	 */
	public function exclude_contents($wp_query) {
		// don't exclude in backend .. redundant
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
		
		
		// *************** Posts *********************
		// hmmm, did not work
		if( get_option('excon_posts_excludes_enable') ) {
			// exclude Posts
			$excon_posts = $this->get_exclude_posts_ids();
			if( count($excon_posts) > 0 ) {
				$wp_query->set('post__not_in', $excon_posts);
			}
		}
	}
		
	
	/**
	 * wandelt ein array in einen sting mit negaiven Vorzeichen um
	 * 
	 * @since	0.1.0
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
	 * Add an Checkbox 
	 * @since 0.2.1
	 */
	public function add_page_checkbox() {
		global $post;
		
		$excon_posts = $this->get_exclude_posts_ids();
		
		$checked = '';
		if( is_object($post) && ($post->ID > 0) ) {
			if( in_array($post->ID, $excon_posts) ) {
				$checked = 'checked="checked"';
			} 
		}
		
		?><div class="misc-pub-section">
			<input type="checkbox" id="excon_exclude_post" name="excon_exclude_post" value="1" <?php echo $checked; ?> />
			<label for="excon_exclude_post"><?php _e('Hide this Post', 'excon')?> <small>(<strong>Plugin: ExcludeContent</strong>)</small></label>
		</div><?php
	}

	
	
	/**
	 * Update der exclude Liste durchführen
	 * 
	 * @since 0.2.1
	 * @param int $page_id
	 */
	public function update_page_excludes($page_id) {
		// verify if this is an auto save routine.
		// If it is our form has not been submitted, so we dont want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;
		
		// If this is a revision, get real post ID
		if ( $parent_id = wp_is_post_revision( $page_id ) )
			$page_id = $parent_id;
		
		// load data
		$excon_posts = $this->get_exclude_posts_ids();

		if($_POST['excon_exclude_post'] == '1' ) {
			// add to list && update
			$excon_posts[] = $page_id;
			update_option('excon_posts_excludes', array_unique($excon_posts));
		
		} else {
			// remove from list
			if( in_array($page_id, $excon_posts) ) {
				$new_data = array();
				foreach($excon_posts as $elm) {
					if($elm != $page_id) 
						$new_data[] = $elm;
				}
				update_option('excon_posts_excludes', $new_data);
			}
		}
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
	 * uninstall hook: delete all Settings
	 *
	 * @since	0.1.0
	 */
	public static function on_uninstall() {
		delete_option('excon_cat_settings');
		delete_option('excon_only_main_query');
		delete_option('excon_posts_excludes_enable');
		delete_option('excon_posts_excludes');
	}

	/**
	 * Registrierung der Settings
	 *
	 * @since	0.1.0
	 */
	public function register_settings() {
		register_setting('exclude_content_settings',  'excon_cat_settings', array($this, 'validate_options_cat'));
		register_setting('exclude_content_settings',  'excon_only_main_query');
		register_setting('exclude_content_settings',  'excon_posts_excludes_enable');
		register_setting('exclude_content_set_posts', 'excon_posts_excludes', array($this, 'validate_options_posts'));
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
					$data[$area][$k] = intval($v);
				}
			} else {
				// wenn das nicht gestzet ist, setzte es
				$data[$area] = array();
			}
		}
		return $data;
	}
	
	/**
	 * Validierung des zweiten Formulatrteils 
	 * @param array $data (Formulardaten)
	 * @return array (int)
	 */
	public function validate_options_posts($data) {
		if(is_array($data)) {
			// only ints
			foreach($data as $k => $v) {
				$data[$k] = intval($v);
			}
		} else {
			$data = array();
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

				<li>
					<input type="checkbox" name="excon_posts_excludes_enable" id="excon_posts_excludes_enable" value="1"  <?php checked(get_option('excon_posts_excludes_enable')); ?> />
					<label for="excon_posts_excludes_enable">Aktiviere das Verstecken von einzelnen Beiträgen.  
					<span>Einzelne Beiträge werden nur dann versteckt, wenn diese Option aktiviert ist.</span></label>
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
			echo '
			<tr '.$class.'>
				<td>'.join('</td><td>', $row).'</td>
			</tr>';
		}
		?>
				</tbody>
			</table>
			<?php submit_button(); ?>
			</form>
			
		<?php
		// Zeige die Liste der exclude_psois_ids 
		$id_array = $this->get_exclude_posts_ids();
		
		if( get_option('excon_posts_excludes_enable') && count($id_array) > 0 ) {
			// get_posts läd alle Einträge, wenn post__in = array() ist
			$args = array(
				'posts_per_page'=> -1,
				'orderby'		=> 'post_date',
				'order'			=> 'DESC',
				'post_type'		=> 'post',
				'post__in'		=> $id_array,
				'suppress_filters' => false
			);
			$list_posts = get_posts( $args );
			?>
			<pre><?php 
			print_r($id_array);
			print_r($args);
			print_r($list_posts);
			?></pre>
			
			?>
			<form method="post" action="options.php">
			<?php settings_fields('exclude_content_set_posts') ?>
				<h3><?php _e('Exclude Posts', 'excon'); ?></h3>
				<ul><?php 
				foreach ( $list_posts as $post ) {
					setup_postdata( $post );
					$id = $post->ID;
					?><li><input type="checkbox" name="excon_posts_excludes[]" id="excon_posts_excludes_<?php echo $id;?>" value="<?php echo $id; ?>" checked="checked" />
					      <label for="excon_posts_excludes_<?php echo $id;?>"><?php $post->post_title; ?> (ID:<?php echo $id;?>)</label></li><?php 
				} ?>
				</ul>
				<?php submit_button(); ?>
			</form>
			<?php 
/*
		
WP_Post Object
(
    [ID] => 3216
    [post_author] => 13
    [post_date] => 2014-02-20 09:07:14
    [post_date_gmt] => 2014-02-17 21:07:14
    [post_content] => 

Checked in to wo das Haus wohnt

    [post_title] => Checked in to wo das Haus wohnt
    [post_excerpt] => 
    [post_status] => publish
    [comment_status] => closed
    [ping_status] => open
    [post_password] => 
    [post_name] => checked-in-to-wo-das-haus-wohnt-81
    [to_ping] => 
    [pinged] => 
    [post_modified] => 2014-02-18 01:26:21
    [post_modified_gmt] => 2014-02-18 00:26:21
    [post_content_filtered] => 
    [post_parent] => 0
    [guid] => http://reclaim.rjani.de/2014/02/checked-in-to-wo-das-haus-wohnt-81/
    [menu_order] => 0
    [post_type] => post
    [post_mime_type] => 
    [comment_count] => 0
    [filter] => raw
)

	*/
		
		
		} else {
			?><p>Keine Einträge in der Exclude-Liste</p><?php
		}
		?>
		</div>
		<?php
	}
}
