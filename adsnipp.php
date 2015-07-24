<?php
/*
Plugin Name: AdSnipp
Description: AdSnipp for Wordpress. Easy way to manage ad snippets.
Version: 3.2.5
Author: AdSnipp
Author URI: http://www.adsnipp.com
License: GNU GPL2
*/

define( 'ADSNIPP_PATH', plugin_dir_path(__FILE__) ); // /path/to/wp-content/plugins/adsnipp/
define( 'ADSNIPP_URL', plugin_dir_url( __FILE__ ) ); // http://www.yoursite.com/wp-content/plugins/adsnipp/
define( 'ADSNIPP_NOT_READY', __( 'Your AdSnipp ads are not ready yet, click <a href="admin.php?page=adsnipp_ads">here</a> to configure.', 'adsnipp' ));

/**
 * $adsnipp_db_version - current database version
 * and used on plugin update to sync database tables
 */
global $adsnipp_db_version;
$adsnipp_db_version = '1.0';

/**
 * register_activation_hook implementation
 *
 * creaing db tables on plugin activation
 */
function adsnipp_install()
{
    global $wpdb;
    global $adsnipp_db_version;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$found_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}adsnipp%';");

	if(!in_array("{$wpdb->prefix}adsnipp_ads", $found_tables)) {
		$sql = "CREATE TABLE `{$wpdb->prefix}adsnipp_ads` (
					`id` INT(11) NOT NULL AUTO_INCREMENT,
					`title` VARCHAR(100) NOT NULL,
					`network` VARCHAR(100) NOT NULL,
					`platform` VARCHAR(10) NOT NULL,
					`script` TEXT NOT NULL,
					`published` TINYINT(1) NOT NULL DEFAULT '0',
					PRIMARY KEY  (`id`)
				);";
		dbDelta($sql);
	}

   	if(!in_array("{$wpdb->prefix}adsnipp_stats", $found_tables)) {
		$sql = "CREATE TABLE `{$wpdb->prefix}adsnipp_stats` (
					`id` INT(11) NOT NULL AUTO_INCREMENT,
					`ad_id` INT(11) NOT NULL,
					`day` INT(15) NOT NULL DEFAULT '0',
					`impressions` INT(11) NOT NULL DEFAULT '0',
					`clicks` INT(11) NOT NULL DEFAULT '0',
					PRIMARY KEY  (`id`),
					INDEX `ad_id` (`ad_id`),
					INDEX `day` (`day`)
				);";
		dbDelta($sql);
	}    

    // save current database version for later use (on upgrade)
    add_option('adsnipp_db_version', $adsnipp_db_version);
}

register_activation_hook(__FILE__, 'adsnipp_install');


/**
 * Trick to update plugin database
 */
function adsnipp_update_db_check()
{
    global $adsnipp_db_version;

    if (get_site_option('adsnipp_db_version') != $adsnipp_db_version) {
        adsnipp_install();
    }
}

add_action('plugins_loaded', 'adsnipp_update_db_check');


if ( is_admin() ) {
	//Including class for displaying records
	require_once ADSNIPP_PATH . 'includes/class-adsnipp-list-table.php';	

	// Add admin notices.
	add_action('admin_notices', 'adsnipp_admin_notices');
} else {
	add_action("wp_enqueue_scripts", 'adsnipp_custom_scripts');
}


/**
 * Administration
 */

/**
 * admin_menu hook implementation, will add pages to list ads and to add new one
 */
function adsnipp_admin_menu()
{
    add_menu_page(__('AdSnipp', 'adsnipp'), __('AdSnipp', 'adsnipp'), 'activate_plugins', 'adsnipp', 'adsnipp_stats_page_handler', ADSNIPP_URL . 'includes/images/menu-icon.png');
	add_submenu_page('adsnipp', __('Stats', 'adsnipp'), __('Stats', 'adsnipp'), 'activate_plugins', 'adsnipp', 'adsnipp_stats_page_handler');
    add_submenu_page('adsnipp', __('My Ads', 'adsnipp'), __('My Ads', 'adsnipp'), 'activate_plugins', 'adsnipp_ads', 'adsnipp_ads_page_handler');
}

add_action('admin_menu', 'adsnipp_admin_menu');

/**
 * Stats page handler
 */
function adsnipp_stats_page_handler()
{
	global $wpdb;

	$stats = array();

	//Yesterday stats
	$yesterday = strtotime('yesterday midnight');

	$stats['yesterday']['impressions'] = $wpdb->get_var("SELECT SUM(`impressions`) FROM {$wpdb->prefix}adsnipp_stats WHERE `day` = " . $yesterday);
	$stats['yesterday']['impressions'] = $stats['yesterday']['impressions'] ? $stats['yesterday']['impressions'] : 0;

	$stats['yesterday']['clicks'] = $wpdb->get_var("SELECT SUM(`clicks`) FROM {$wpdb->prefix}adsnipp_stats WHERE `day` = " . $yesterday);
	$stats['yesterday']['clicks'] = $stats['yesterday']['clicks'] ? $stats['yesterday']['clicks'] : 0;


	//Last 8 days stats
	$from = strtotime('today midnight') - 7 * 86400;

	$stats['8days']['desktop'] = $wpdb->get_results("SELECT s.ad_id, a.title, SUM(clicks) AS total_clicks, 
														SUM(impressions) AS total_impressions, SUM(clicks+ impressions ) AS total
													FROM wp_adsnipp_stats AS s
													JOIN wp_adsnipp_ads AS a ON s.ad_id = a.id
													WHERE a.platform = 'Desktop' AND s.day >= " . $from . "
													GROUP BY ad_id
													ORDER BY total DESC
													LIMIT 5");
	$stats['8days']['mobile'] = $wpdb->get_results("SELECT s.ad_id, a.title, SUM(clicks) AS total_clicks, 
														SUM(impressions) AS total_impressions, SUM(clicks+ impressions ) AS total
													FROM wp_adsnipp_stats AS s
													JOIN wp_adsnipp_ads AS a ON s.ad_id = a.id
													WHERE a.platform = 'Mobile' AND s.day >= " . $from . "
													GROUP BY ad_id
													ORDER BY total DESC
													LIMIT 5");
?>
<div class="wrap">
    <h2><?php _e('Stats', 'adsnipp')?> <a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=adsnipp_ads');?>"><?php _e('My Ads', 'adsnipp')?></a>
    </h2>

	<h3><?php _e('Overview', 'adsnipp')?></h3>

	<table class="wp-list-table widefat fixed striped" style="width: 30%;">
		<thead>
		<tr>
			<th colspan="2" class="manage-column" scope="col"><b><?php _e('Yesterday', 'adsnipp')?></b> <?php _e('(across all of your ads)', 'adsnipp')?></th>
		</tr>
		</thead>

		<tbody>
			<tr>
				<td><?php _e('Impressions', 'adsnipp')?></td>
				<td><?php echo $stats['yesterday']['impressions']; ?></td>
			</tr>

			<tr>
				<td><?php _e('Clicks', 'adsnipp')?></td>
				<td><?php echo $stats['yesterday']['clicks']; ?></td>
			</tr>	
		</tbody>
	</table>

	<table style="width: 100%">
		<tr valign="top">
			<td style="width: 50%">
				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th colspan="3" class="manage-column" scope="col"><b><?php _e('Top Ads Desktop', 'adsnipp')?></b> <?php _e('(across all of your ads in last 8 days)', 'adsnipp')?></th>
					</tr>
					</thead>
<?php
	adsnipp_top_ads_html($stats['8days']['desktop']);
?>
				</table>
			</td>
			<td style="width: 50%">
				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th colspan="3" class="manage-column" scope="col"><b><?php _e('Top Ads Mobile', 'adsnipp')?></b> <?php _e('(across all of your ads in last 8 days)', 'adsnipp')?></th>
					</tr>
					</thead>
<?php
	adsnipp_top_ads_html($stats['8days']['mobile']);
?>
				</table>
			</td>
		</tr>
	</table>
</div>
<?php
}

/**
 * Outputs top ads html
 */
function adsnipp_top_ads_html($ads) {
?>
					<tbody>
						<tr>
							<td><?php _e('Ad', 'adsnipp')?></td>
							<td><?php _e('Impressions', 'adsnipp')?></td>
							<td><?php _e('Clicks', 'adsnipp')?></td>
						</tr>
<?php
	if (count($ads)) {
		foreach ($ads as $ad) {
			echo '
						<tr>
							<td>
								' . $ad->title . '
							</td>
							<td>
								' . ($ad->total_impressions ? $ad->total_impressions : 0). '
							</td>
							<td>
								' . ($ad->total_clicks ? $ad->total_clicks : 0). '
							</td>
						</tr>'; 		
		}				
	} else {
		echo '
						<tr>
							<td colspan="3">
							No stats found.
							</td>
						</tr>'; 
	}
?>
					</tbody>
<?php
}

/**
 * List page handler
 */
function adsnipp_ads_page_handler()
{
	if (isset($_GET['view']) && $_GET['view'] == 'ad_form') {
		adsnipp_ad_form_page_handler();
	} else {
		global $wpdb;

		$table = new Adsnipp_List_Table();
		$table->prepare_items();

		$message = '';
		if ('on' === $table->current_action()) {
			$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items On: %d', 'adsnipp'), count($_REQUEST['id'])) . '</p></div>';

		} else if ('off' === $table->current_action()) {
			$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items Off: %d', 'adsnipp'), count($_REQUEST['id'])) . '</p></div>';

		} else if ('delete' === $table->current_action()) {
			$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'adsnipp'), count($_REQUEST['id'])) . '</p></div>';

		}
    ?>
<div class="wrap">

    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('My Ads', 'adsnipp')?> <a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=adsnipp_ads&view=ad_form');?>"><?php _e('Add New', 'adsnipp')?></a>
    </h2>
    <?php echo $message; ?>

    <form id="ads-table" method="GET">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
        <?php $table->display() ?>
    </form>

</div>
<?php
	}
}

/**
 * Form for adding/editing row
 */

/**
 * Form page handler checks is there some data posted and tries to save it
 * Also it renders basic wrapper in which we are calling meta box render
 */
function adsnipp_ad_form_page_handler()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'adsnipp_ads';

    $message = '';
    $notice = '';

    // default $item
    $default = array(
		'id'		=> 0,
		'title'		=> '',
		'platform'	=> '',
		'network'	=> '',
		'script'	=> '',
		'published'	=> 0,
    );

	if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
		$_REQUEST = stripslashes_deep( $_REQUEST );

		if (isset($_REQUEST['title'])) {
			$_REQUEST['title'] = htmlspecialchars(trim($_REQUEST['title']), ENT_QUOTES);
		}
		if (isset($_REQUEST['network'])) {
			$_REQUEST['network'] = htmlspecialchars(trim($_REQUEST['network']), ENT_QUOTES);
		}
		if (isset($_REQUEST['script'])) {
			$_REQUEST['script'] = htmlspecialchars(trim($_REQUEST['script'], "\t\n "), ENT_QUOTES);
		}
		
		$item = shortcode_atts($default, $_REQUEST);

		$item_valid = adsnipp_validate_ad($item);
        if ($item_valid === true) {
            if ($item['id'] == 0) {
                $result = $wpdb->insert($table_name, $item);
                $item['id'] = $wpdb->insert_id;
                if ($result) {
                    $message = __('Ad was successfully saved', 'adsnipp');
					if (!get_option('adsnipp_registered')) {
						adsnipp_register();
					}
                } else {
                    $notice = __('There was an error while saving ad', 'adsnipp');
                }
            } else {
                $result = $wpdb->update($table_name, $item, array('id' => $item['id']));
                if ($result) {
                    $message = __('Ad was successfully updated', 'adsnipp');
                } else {
                    $notice = __('There was an error while updating ad', 'adsnipp');
                }
            }
        } else {
            $notice = $item_valid;
        }
    }
    else {
        $item = $default;
        if (isset($_REQUEST['id'])) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_REQUEST['id']), ARRAY_A);
            if (!$item) {
                $item = $default;
                $notice = __('Ad not found', 'adsnipp');
            }
        }
    }

    // here we adding our custom meta box
    add_meta_box('ad_form_meta_box', 'Ad data', 'adsnipp_ad_form_meta_box_handler', 'ad', 'normal', 'default');

    ?>
<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Ad', 'adsnipp')?> <a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=adsnipp_ads');?>"><?php _e('Back to My Ads', 'adsnipp')?></a>
    </h2>

    <?php if (!empty($notice)): ?>
    <div id="notice" class="error"><p><?php echo $notice ?></p></div>
    <?php endif;?>
    <?php if (!empty($message)): ?>
    <div id="message" class="updated"><p><?php echo $message ?></p></div>
    <?php endif;?>

    <form id="form" method="POST">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
        <input type="hidden" name="id" value="<?php echo $item['id'] ?>"/>

        <div class="metabox-holder" id="poststuff">
            <div id="post-body">
                <div id="post-body-content">
                    <?php do_meta_boxes('ad', 'normal', $item); ?>
                    <input type="submit" value="<?php _e('Save', 'adsnipp')?>" id="submit" class="button-primary" name="submit">
                </div>
            </div>
        </div>
    </form>
	<p class="submit">
		<?php _e('*By pressing "Save" I confirm I have read and accepted AdSnipp', 'adsnipp')?> <a href="http://adsipp.com/terms" target="_blank" style="font-weight: bold"><?php _e('terms of use', 'adsnipp')?></a> 
	</p>
	<p>For support, mail us at: <a href="mailto:support@adsnipp.com">support@adsnipp.com</a></p>
</div>
<?php
}

/**
 * This function renders custom meta box
 * $item is row
 *
 * @param $item
 */
function adsnipp_ad_form_meta_box_handler($item)
{
    ?>

<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="title"><?php _e('Title', 'adsnipp')?></label>
        </th>
        <td>
            <input id="title" name="title" type="text" style="width: 95%" value="<?php echo esc_attr($item['title'])?>" size="50" maxlength="100" class="code" placeholder="<?php _e('Write your title here...', 'adsnipp')?>" required>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="platform"><?php _e('Platform', 'adsnipp')?></label>
        </th>
        <td>
            <select id="platform" name="platform" style="width: 20%" class="code" required>
				<option value="Desktop"<?php echo ( $item['platform'] == 'Desktop' ? ' selected="true"' : '' ); ?>>Desktop</option>
				<option value="Mobile"<?php echo ( $item['platform'] == 'Mobile' ? ' selected="true"' : '' ); ?>>Mobile</option>
			</select>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="network"><?php _e('Ad Network', 'adsnipp')?></label>
        </th>
        <td>
            <input id="network" name="network" type="text" style="width: 95%" value="<?php echo esc_attr($item['network'])?>" size="50" maxlength="100" class="code" placeholder="<?php _e('Ad network...', 'adsnipp')?>" required>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="script"><?php _e('Ad Network Script', 'adsnipp')?></label>
        </th>
        <td>
            <textarea id="script" name="script" style="width: 95%" class="code" placeholder="<?php _e('Paste here your script you got from your Ad network.', 'adsnipp')?>" required><?php echo esc_attr($item['script'])?></textarea>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="script"><?php _e('Status', 'adsnipp')?></label>
        </th>
        <td>
			<select id="published" name="published" style="width: 20%" class="code" required>
				<option value="1"<?php echo ( $item['published'] ? ' selected="true"' : '' ); ?>>On</option>
				<option value="0"<?php echo ( !$item['published'] ? ' selected="true"' : '' ); ?>>Off</option>
			</select>
        </td>
    </tr>
    </tbody>
</table>
<?php
}

/**
 * Validates data and retrieve bool on success
 * and error message(s) on error
 *
 * @param $item
 * @return bool|string
 */
function adsnipp_validate_ad($item)
{
    $messages = array();

    if (empty($item['title'])) {
		$messages[] = __('Title is required', 'adsnipp');
	}

    if (empty($item['network'])) {
		$messages[] = __('Ad Network is required', 'adsnipp');
	}

    if (empty($item['script'])) {
		$messages[] = __('Ad Network Script is required', 'adsnipp');
	}

    if (empty($messages)) {
		return true;
	} else {
		return implode('<br />', $messages);
	}
}

/**
 * For later
 */
/*function adsnipp_languages()
{
    load_plugin_textdomain('adsnipp', false, dirname(plugin_basename(__FILE__)) . '/language');
}

add_action('init', 'adsnipp_languages');*/



/**
 * Publish active ads at Desktop/Mobile incrementing impressions
 */
function adsnipp_publish_ads(){    
	global $wpdb;
    
	$platform = wp_is_mobile() ? 'Mobile' : 'Desktop';

	$items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}adsnipp_ads WHERE platform = '" . $platform . "' AND published = 1");
	if (count($items)) {
		foreach ($items as $item) {
			echo '<div class="gofollow" data-track="' . $item->id . '">' . htmlspecialchars_decode($item->script, ENT_QUOTES) . '</div>';
			adsnipp_add_impression($item->id);
		}
	}
}    

add_action('wp_head', 'adsnipp_publish_ads');


/**
 * Add impressions
 */
function adsnipp_add_impression($ad_id) { 
	global $wpdb;

	$today = strtotime('today midnight');

	$stats = $wpdb->get_var($wpdb->prepare("SELECT `id` FROM {$wpdb->prefix}adsnipp_stats WHERE `ad_id` = %d AND `day` = $today;", $ad_id));

	if($stats) {
		$wpdb->query("UPDATE {$wpdb->prefix}adsnipp_stats SET `impressions` = `impressions` + 1 WHERE `id` = $stats;");
	} else {
		$wpdb->insert($wpdb->prefix.'adsnipp_stats', array('ad_id' => $ad_id, 'day' => $today, 'clicks' => 0, 'impressions' => 1));
	}

} 

/**
 * Count impressions
 */
function adsnipp_count_impression($ad_id, $period = 'all') { 
	global $wpdb;

	$count = $wpdb->get_var($wpdb->prepare("SELECT SUM(`impressions`) FROM {$wpdb->prefix}adsnipp_stats WHERE `ad_id` = %d;", $ad_id));

	if (!$count) {
		$count = 0;
	}

	return $count;
} 

/**
 * Count clicks
 */
function adsnipp_count_clicks($ad_id, $period = 'all') { 
	global $wpdb;

	$count = $wpdb->get_var($wpdb->prepare("SELECT SUM(`clicks`) FROM {$wpdb->prefix}adsnipp_stats WHERE `ad_id` = %d;", $ad_id));

	if (!$count) {
		$count = 0;
	}

	return $count;
} 

/**
 * Add clicks
 */
function adsnipp_click_callback() {
	global $wpdb;

	$ad_id = esc_attr($_POST['track']);

	if(is_numeric($ad_id)) {
		$id = $wpdb->get_var($wpdb->prepare("SELECT `id` FROM `".$wpdb->prefix."adsnipp_ads` WHERE `id` = %d;", $ad_id));
		if ($id) {
			$today = strtotime('today midnight');

			$stats = $wpdb->get_var($wpdb->prepare("SELECT `id` FROM `".$wpdb->prefix."adsnipp_stats` WHERE `ad_id` = %d AND `day` = $today;", $ad_id));
			if($stats) {
				$wpdb->query("UPDATE `".$wpdb->prefix."adsnipp_stats` SET `clicks` = `clicks` + 1 WHERE `id` = $stats;");
			} else {
				$wpdb->insert($wpdb->prefix.'adsnipp_stats', array('ad_id' => $ad_id, 'day' => $today, 'clicks' => 1, 'impressions' => 1));
			}
		}
	}

	wp_die();
}

add_action('wp_ajax_adsnipp_click', 'adsnipp_click_callback');

/**
 * Include custom scripts
 */
function adsnipp_custom_scripts() {
	wp_enqueue_script('clicktrack-adsnipp', ADSNIPP_URL . 'includes/jquery.adsnipp.clicktracker.js', false, null, true);
}

/**
 * Admin Notices
 */
function adsnipp_admin_notices() {
	if (!get_option('adsnipp_registered')) {
		echo '
			<div class="error adsnipp" style="text-align: center; ">
				<p style="color: red; font-size: 14px; font-weight: bold;">' . 
					ADSNIPP_NOT_READY . '
				</p>
			</div>';

		// WP Pointers
		$seen_it = explode(',', get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));
		if (!in_array('adsnipp', $seen_it)) {
			adsnipp_popup_setup();
		}

		
	}
}

/**
 * Admin Notices Popup
 */
function adsnipp_popup_setup() { 
	wp_enqueue_style( 'wp-pointer' ); 
	wp_enqueue_script( 'jquery-ui' ); 
	wp_enqueue_script( 'wp-pointer' ); 
	wp_enqueue_script( 'utils' );
	?>
	<style>
		#adsnipp-popup-header {background-color: #D81378; border-color: #D81378;}
		#adsnipp-popup-header:before {color:#D81378;}
	</style>
	<script type="text/javascript">
		//<![CDATA[
		;(function($) {
			var setup = function() {
				$('#toplevel_page_adsnipp').pointer({
						content: '<h3 id="adsnipp-popup-header"><?php echo ADSNIPP_NOT_READY;?></h3>',
						position: {
							edge: 'left', // arrow direction
							align: 'center' // vertical alignment
						},
						pointerWidth: 350,
						close: function() {
							$.post(ajaxurl, {
								pointer: 'adsnipp',
								action: 'dismiss-wp-pointer'
							});
						}
				}).pointer('open');
			};
			$(window).bind('load.wp-pointers', setup);
		})(jQuery);
		//]]>
	</script>
	<?php
}

/**
 * Register
 */
function adsnipp_register() {
	add_option('adsnipp_registered', 1);
}