<?php
/**
 * Plugin Name: Efficient Related Posts
 * Plugin URI: http://xavisys.com/2009/06/efficient-related-posts/
 * Description: A related posts plugin that works quickly even with thousands of posts and tags
 * Version: 0.2.3
 * Author: Aaron D. Campbell
 * Author URI: http://xavisys.com/
 */
/**
 *	Changelog:
 * 		06/16/2009 - 0.2.3:
 *			- Released via WordPress.org
 *
 * 		06/15/2009 - 0.2.2:
 *			- Fixed issue with title not displaying
 *			- Renamed in anticipation of adding to WordPress.org
 *
 * 		04/24/2009 - 0.2.1:
 *			- When spidering though related posts, limit the posts that are checked
 *
 * 		02/18/2009 - 0.2.0:
 *			- First run of processing posts in chunks
 *
 * 		02/18/2009 - 0.1.4:
 *			- Fixed array_slice error that showed up when there were no related posts
 *			- Fixed the issue with the "No Related Posts" text not showing
 *
 * 		02/18/2009 - 0.1.3:
 *			- Formatted Admin page warning correctly
 *
 * 		02/18/2009 - 0.1.2:
 *			- Added all copy and made it all translatable for future application
 *
 * 		02/18/2009 - 0.1.1:
 *			- MySQL query optimizations to reduce processing time
 *
 * 		02/17/2009 - 0.1.0:
 *			- Added all settings to admin page
 *			- Added helper functions for displaying
 *			- Added ability to add related posts to RSS
 *			- Added ability to ignore categories from matches
 *			- Added ability to automatically add to posts
 *			- Added ability to specify title
 *			- Added ability to specify text to display if no related posts exist
 *
 * 		02/16/2009 - 0.0.4:
 *			- Added admin page to process posts - still needs serious cleanup
 *
 * 		02/16/2009 - 0.0.3:
 *			- Processes all posts
 *
 * 		02/15/2009 - 0.0.2:
 *			- Processes Post now
 *
 * 		02/12/2009 - 0.0.1:
 *			- Original Version
 */
/**
 * efficientRelatedPosts is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */
class efficientRelatedPosts {
	/**
	 * Static property to hold our singleton instance
	 */
	static $instance = false;

	/**
	 * @var array Plugin settings
	 */
	private $_settings;

	/**
	 * @var array Posts Processed
	 */
	private $_processedPosts = array();

	/**
	 * This is our constructor, which is private to force the use of getInstance()
	 * @return void
	 */
	private function __construct() {
		$this->_getSettings();
		if ( $this->_settings['auto_insert'] != 'no' ) {
			add_filter('the_content', array( $this, 'filterPostContent'), 99);
		}
		if ( $this->_settings['rss'] == 'yes' ) {
			add_filter('the_content', array( $this, 'filterPostContentRSS'), 1);
		}
	}

	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * This adds the options page for this plugin to the Options page
	 *
	 * @access public
	 */
	public function admin_menu() {
		add_options_page(__('Efficient Related Posts', 'efficient_related_posts'), __('Related Posts', 'efficient_related_posts'), 'manage_options', 'efficientRelatedPosts', array($this, 'options'));
	}

	public function registerOptions() {
		register_setting( 'erp-options', 'erp' );
	}

	public function processPosts() {
		if ( isset($_GET['page']) && $_GET['page'] == 'efficientRelatedPosts' && isset($_POST['process_posts']) ) {
			//ini_set('memory_limit', '256M');
			check_admin_referer( 'erp-processPosts' );
			$timestart = explode(' ', microtime() );
			$timestart = $timestart[1] + $timestart[0];

			$processed = $this->processAllPosts($_POST['erp']);

			$timeend = explode(' ',microtime());
			$timeend = $timeend[1] + $timeend[0];
			$timetotal = $timeend-$timestart;
			$r = ( function_exists('number_format_i18n') ) ? number_format_i18n($timetotal, 1) : number_format($timetotal, 1);

			$notice = "Processed: " . implode(', ', $processed) . " in {$r} seconds";
			$notice = str_replace( "'", "\'", "<div class='updated'><p>$notice</p></div>" );
			add_action('admin_notices', create_function( '', "echo '$notice';" ) );
		}
	}

	/**
	 * This is used to display the options page for this plugin
	 */
	public function options() {
		$this->_getSettings();
?>
		<div class="wrap">
			<h2><?php _e('Efficient Related Posts', 'efficient_related_posts') ?></h2>
			<h3><?php _e('General Settings', 'efficient_related_posts') ?></h3>
			<form action="options.php" method="post">
				<?php settings_fields( 'erp-options' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="erp_title"><?php _e("Title:",'efficient_related_posts'); ?></label>
						</th>
						<td>
							<input id="erp_title" name="erp[title]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['title']); ?>" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_no_rp_text"><?php _e("Display Text When No Related Posts Found:",'efficient_related_posts'); ?></label>
						</th>
						<td>
							<input id="erp_no_rp_text" name="erp[no_rp_text]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['no_rp_text']); ?>" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_ignore_cats"><?php _e('Ignore Categories:', 'efficient_related_posts'); ?></label>
						</th>
						<td>
							<input id="erp_ignore_cats" name="erp[ignore_cats]" type="text" class="regular-text code" value="<?php echo attribute_escape(implode(',', $this->_settings['ignore_cats'])); ?>" size="40" />
							<span class="setting-description"><?php _e('Comma Separated Category IDs', 'efficient_related_posts'); ?></span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_max_relations_stored"><?php _e('Max Related Posts to Store:', 'efficient_related_posts'); ?></label>
						</th>
						<td>
							<input id="erp_max_relations_stored" name="erp[max_relations_stored]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['max_relations_stored']); ?>" size="40" />
							<span class="setting-description"><?php _e("Max number to store.  You can't display more than this.", 'efficient_related_posts'); ?></span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_num_to_display"><?php _e('Number of Related Posts to Display:', 'efficient_related_posts'); ?></label>
						</th>
						<td>
							<input id="erp_num_to_display" name="erp[num_to_display]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['num_to_display']); ?>" size="40" />
							<span class="setting-description"><?php _e('The number of related posts to display if none is specified.', 'efficient_related_posts'); ?></span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php _e("Other Setting:",'efficient_related_posts');?>
						</th>
						<td>
							<input name="erp[auto_insert]" id="erp_auto_insert_no" type="radio" value="no"<?php checked('no', $this->_settings['auto_insert']) ?>>
							<label for="erp_auto_insert_no">
								<?php _e("Do Not Auto Insert Into Posts",'efficient_related_posts');?>
							</label>
							<br />
							<input name="erp[auto_insert]" id="erp_auto_insert_all" type="radio" value="all"<?php checked('all', $this->_settings['auto_insert']) ?>>
							<label for="erp_auto_insert_all">
								<?php _e("Auto Insert Into Posts",'efficient_related_posts');?>
							</label>
							<br />
							<input name="erp[auto_insert]" id="erp_auto_insert_single" type="radio" value="single"<?php checked('single', $this->_settings['auto_insert']) ?>>
							<label for="erp_auto_insert_single">
								<?php _e("Auto Insert Into Only Single Posts",'efficient_related_posts');?>
							</label>
							<br />
							<br />
							<input name="erp[rss]" id="erp_rss" type="checkbox" value="yes"<?php checked('yes', $this->_settings['rss']) ?>>
							<label for="erp_rss">
								<?php _e("Related Posts for RSS",'efficient_related_posts');?>
							</label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;', 'efficient_related_posts'); ?>" />
				</p>
			</form>
			<h3 style="margin:2em 0 .5em;"><?php _e('Build Relations', 'efficient_related_posts') ?></h3>
			<p style="margin:0;">
				<?php _e('Use this to build relationships for all posts.', 'efficient_related_posts'); ?>
			</p>
			<p class="error"><?php _e('Warning, this could take a very long time (in test it took about 1 hour for 2000 posts).', 'efficient_related_posts'); ?></p>
			<form action="" method="post">
				<?php wp_nonce_field('erp-processPosts'); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<?php _e('Posts/Pages to process:', 'efficient_related_posts') ?>
						</th>
						<td>
							<input type="checkbox" name="erp[drafts]" value="true" id="erp-process-drafts" />
							<label for="erp-process-drafts"><?php _e('Process drafts', 'efficient_related_posts'); ?></label><br />
							<input type="checkbox" name="erp[pending]" value="true" id="erp-process-pending" />
							<label for="erp-process-pending"><?php _e('Process pending posts', 'efficient_related_posts'); ?></label><br />
							<input type="checkbox" name="erp[scheduled]" value="true" id="erp-process-scheduled" />
							<label for="erp-process-scheduled"><?php _e('Process scheduled posts', 'efficient_related_posts'); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="process_posts" value="<?php _e('Process Posts/Pages', 'efficient_related_posts'); ?>" />
				</p>
			</form>
<?php
		if (get_option('erp-processedPosts')) {
?>
			<h3 style="margin:2em 0 .5em;"><?php _e('Continue Processing Posts/Pages'); ?></h3>
			<p><?php _e("The last processing didn't complete.  If you want to continue where it left off, use this:"); ?></p>
			<form action="" method="post">
				<?php wp_nonce_field('erp-processPosts'); ?>
				<input type="hidden" name="erp[drafts]" value="<?php echo $_POST['erp']['drafts']; ?>" />
				<input type="hidden" name="erp[pending]" value="<?php echo $_POST['erp']['pending']; ?>" />
				<input type="hidden" name="erp[scheduled]" value="<?php echo $_POST['erp']['scheduled']; ?>" />
				<input type="hidden" name="erp[continue]" value="true" />
				<p class="submit">
					<input type="submit" name="process_posts" value="<?php _e('Continue Processing'); ?>" />
				</p>
			</form>
<?php
		}
?>
		</div>
<?php
	}

	private function _findRelations($post, $processRelated = false, $postIds = null) {
		// Try to increase the time limit
		set_time_limit(60);
		global $wpdb;
		$now = current_time('mysql', 1);
		$post = get_post($post);
		$tags = wp_get_post_tags($post->ID);

		if ( !empty($tags) ) {

			$tagList = array();
			foreach ( $tags as $t ) {
				$tagList[] = $t->term_id;
			}

			$tagList = implode(',', $tagList);

			if ( !empty($postIds) ) {
				// Make sure each element is an integer and filter out any 0s
				$postIds = array_diff(array_walk($postIds, 'absint'), array('','0'));
			}
			if ( !empty($postIds) ) {
				// If it's still not empty, make a SQL WHERE clause
				$postIds = 'p.ID IN (' . implode(',', $postIds) . ') AND';
			} else {
				// If it's empty, make sure it's a string so we don't get notices
				$postIds = '';
			}

			$q = <<<QUERY
			SELECT
				p.ID,
				count(t_r.object_id) as matches
			FROM
				{$wpdb->term_taxonomy} t_t,
				{$wpdb->term_relationships} t_r,
				{$wpdb->posts} p
			WHERE
				{$postIds}
				t_t.taxonomy ='post_tag' AND
				t_t.term_taxonomy_id = t_r.term_taxonomy_id AND
				t_r.object_id  = p.ID AND
				(t_t.term_id IN ({$tagList})) AND
				p.ID != {$post->ID} AND
				p.post_status = 'publish' AND
				p.post_date_gmt < '{$now}'
			GROUP BY
				t_r.object_id
			ORDER BY
				matches DESC,
				p.post_date_gmt DESC

QUERY;
			$related_posts = $wpdb->get_results($q);

			$allRelatedPosts = array();
			$relatedPostsToStore = array();
			$threshold = '';

			if ($related_posts) {
				foreach ($related_posts as $related_post ){
					$overlap = array_intersect(wp_get_post_categories($related_post->ID), $this->_settings['ignore_cats']);

					$allRelatedPosts[] = $related_post;

					if ( empty($overlap) && count($relatedPostsToStore) < $this->_settings['max_relations_stored'] ) {
						$threshold = $related_post->matches;
						$relatedPostsToStore[] = $related_post->ID;
					}
				}
			}

			if (!add_post_meta($post->ID, '_efficient_related_posts', $relatedPostsToStore, true)) {
				update_post_meta($post->ID, '_efficient_related_posts', $relatedPostsToStore);
			}

			/**
			 * The threshold is the lowest number of matches in the related posts
			 * that we store.  We use this to see if we need to process an old post.
			 */
			if (!add_post_meta($post->ID, '_relation_threshold', $threshold, true)) {
				update_post_meta($post->ID, '_relation_threshold', $threshold);
			}

			if ($processRelated) {
				foreach ( $allRelatedPosts as $p ) {
					$threshold = get_post_meta($p->ID, '_relation_threshold', true);

					if ( empty($threshold) || $threshold < $p->matches ) {
						// Get the current related posts
						$relatedPosts = get_post_meta($p->ID, '_efficient_related_posts', true);
						// Add current post to the list
						$relatedPosts[] = $post->ID;
						// Find the relations, but limit the posts that are checked to save memory/time
						$this->_findRelations( $p->ID, false, $relatedPosts );
					}
				}
			}
		}
	}

	public function processPost( $a ) {
		$a = get_post( $a );
		// Don't Process revisions
		if ( $a->post_type == 'revision' ) {
			return;
		}
		$this->_findRelations( $a, true );
	}

	public function processAllPosts( $args ) {
		//set_time_limit(600);
		global $wpdb;
		$defaults = array(
			'drafts'	=> false,
			'pending'	=> false,
			'scheduled'	=> false,
			'continue'	=> false
		);

		$args = wp_parse_args((array) $_POST['erp'], $defaults);

		$statuses = array('publish');

		if ( $args['drafts'] == 'true' ) {
			$statuses[] = 'draft';
		}
		if ( $args['scheduled'] == 'true' ) {
			$statuses[] = 'future';
		}
		if ( $args['pending'] == 'true' ) {
			$statuses[] = 'pending';
		}

		$q = "SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_status` IN ('%s')";
		if ( $args['continue'] ) {
			$this->_processedPosts = get_option('erp-processedPosts');
			if ( !empty($this->_processedPosts) && is_array($this->_processedPosts) ) {
				$q .= ' && `ID` NOT IN (' . implode(',', $this->_processedPosts) . ')';
			} else {
				$this->_processedPosts = array();
			}
		}
		$q = sprintf( $q, implode("','", $statuses));

		$postIDs = $wpdb->get_col( $q );

		foreach ($postIDs as $pid) {
			$this->_findRelations( $pid );
			$this->_processedPosts[] = $pid;
			update_option('erp-processedPosts', $this->_processedPosts);
			if (memory_get_usage() >= .8 * get_memory_limit()) {
				break;
			}
		}
		delete_option('erp-processedPosts');
		return $postIDs;
	}

	public function getRelatedPosts( $args = array() ) {
		global $post;
		$output = '';

		$settings = wp_parse_args($args, $this->_settings);

		$relatedPosts = get_post_meta($post->ID, '_efficient_related_posts', true);

		if ( empty($relatedPosts) || $settings['num_to_display'] == 0 ){
			$output .= "<li>{$settings['no_rp_text']}</li>";
		} else {
			$relatedPosts = array_slice($relatedPosts, 0, $settings['num_to_display']);
			foreach ( $relatedPosts as $pid ) {
				$related_post = get_post($pid);
				$link = '<a href="' . get_permalink($related_post->ID).'" title="'.attribute_escape(wptexturize($related_post->post_title)).'">'.wptexturize($related_post->post_title).'</a>';
				$output .= "<li>{$link}</li>";
			}
		}

		$output = "<ul class='related_post'>{$output}</ul>";

		if ( !empty($settings['title']) ) {
			$output = "<h3 class='related_post_title'>{$settings['title']}</h3>{$output}";
		}

		return $output;
	}

	public function relatedPosts( $args = array() ) {
		echo $this->getRelatedPosts($args);
	}

	public function filterPostContent($content) {
		// We don't want to filter if this is a feed or if settings tell us not to
		if ( ($this->_settings['auto_insert'] == 'all' || ( $this->_settings['auto_insert'] == 'single' && is_single() ) ) && !is_feed() ) {
			$content .= $this->getRelatedPosts();
		}

		return $content;
	}

	public function filterPostContentRSS($content) {
		if ( $this->_settings['rss'] == 'yes' && is_feed() ) {
			$content .= $this->getRelatedPosts();
		}

		return $content;
	}

	private function _getSettings() {
		$defaults = array(
			'title'					=> __("Related Posts:",'efficient_related_posts'),
			'no_rp_text'			=> __("No Related Posts",'efficient_related_posts'),
			'ignore_cats'			=> array(),
			'max_relations_stored'	=> 10,
			'num_to_display'		=> 5,
			'auto_insert'			=> 'no',
			'rss'					=> 'no'
		);
		$this->_settings = get_option('erp');
		$this->_settings = wp_parse_args($this->_settings, $defaults);

		if ( !is_array($this->_settings['ignore_cats']) ) {
			$this->_settings['ignore_cats'] = preg_split('/\s*,\s*/', trim($this->_settings['ignore_cats']), -1, PREG_SPLIT_NO_EMPTY);
		}
		$this->_settings['max_relations_stored'] = intval($this->_settings['max_relations_stored']);
		$this->_settings['num_to_display'] = intval($this->_settings['num_to_display']);
	}
}

/**
 * Helper functions
 */

function wp_related_posts( $args = array() ) {
	// Instantiate our class
	$efficientRelatedPosts = efficientRelatedPosts::getInstance();
	$efficientRelatedPosts->relatedPosts($args);
}

function wp_get_related_posts( $args = array() ) {
	// Instantiate our class
	$efficientRelatedPosts = efficientRelatedPosts::getInstance();
	return $efficientRelatedPosts->getRelatedPosts($args);
}

if ( !function_exists('get_memory_limit') ) {
	function get_memory_limit() {
		$limit = ini_get('memory_limit');
		$symbol = array('B', 'K', 'M', 'G');
		$numLimit = (int) $limit;
		$units = str_replace($numLimit, '', $limit);

		if ( empty($units) ) {
			return $numLimit;
		} else {
			return $numLimit * pow(1024, array_search(strtoupper($units[0]), $symbol));
		}
	}
}

// Instantiate our class
$efficientRelatedPosts = efficientRelatedPosts::getInstance();

/**
 * Add filters and actions
 */
add_action( 'save_post', array( $efficientRelatedPosts, 'processPost' ) );
add_action( 'admin_menu', array( $efficientRelatedPosts, 'admin_menu' ) );
add_action( 'admin_init', array( $efficientRelatedPosts, 'processPosts' ) );
add_action( 'admin_init', array( $efficientRelatedPosts, 'registerOptions' ) );

/**
 * For use with debugging
 * @todo Remove this
 */
if ( !function_exists('dump') ) {
	function dump($v, $title = '') {
		if (!empty($title)) {
			echo '<h4>' . htmlentities($title) . '</h4>';
		}
		echo '<pre>' . htmlentities(print_r($v, true)) . '</pre>';
	}
}
