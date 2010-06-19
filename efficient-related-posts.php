<?php
/**
 * Plugin Name: Efficient Related Posts
 * Plugin URI: http://xavisys.com/wordpress-plugins/efficient-related-posts/
 * Description: A related posts plugin that works quickly even with thousands of posts and tags
 * Version: 0.3.6
 * Author: Aaron D. Campbell
 * Author URI: http://xavisys.com/
 * Text Domain: efficient-related-posts
 */

/**
 * efficientRelatedPosts is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */
require_once('xavisys-plugin-framework.php');
class efficientRelatedPosts extends XavisysPlugin {
	/**
	 * @var efficientRelatedPosts - Static property to hold our singleton instance
	 */
	static $instance = false;

	/**
	 * @var array Posts Processed
	 */
	private $_processedPosts = array();

	protected function _init() {
		$this->_hook = 'efficientRelatedPosts';
		$this->_file = plugin_basename( __FILE__ );
		$this->_pageTitle = __( 'Efficient Related Posts', $this->_slug );
		$this->_menuTitle = __( 'Related Posts', $this->_slug );
		$this->_accessLevel = 'manage_options';
		$this->_optionGroup = 'erp-options';
		$this->_optionNames = array('erp');
		$this->_optionCallbacks = array();
		$this->_slug = 'efficient-related-posts';
		$this->_paypalButtonId = '9996714';

		/**
		 * Add filters and actions
		 */
		add_action( 'save_post', array( $this, 'processPost' ) );
		add_action( 'admin_init', array( $this, 'processPosts' ) );
		add_action( 'permalink_structure_changed', array( $this, 'fixPermalinks' ) );
		add_shortcode('relatedPosts', array($this, 'handleShortcodes'));
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_filter( $this->_slug .'-opt-erp', array( $this, 'filterSettings' ) );
		add_action( 'erp-show-related-posts', array( $this, 'relatedPosts' ) );
		add_filter( 'erp-get-related-posts', array( $this, 'getRelatedPosts' ) );
	}

	protected function _postSettingsInit() {
		if ( $this->_settings['erp']['auto_insert'] != 'no' ) {
			add_filter('the_content', array( $this, 'filterPostContent'), 99);
		}
		if ( $this->_settings['erp']['rss'] == 'yes' ) {
			add_filter('the_content', array( $this, 'filterPostContentRSS'), 1);
		}
	}

	public function addOptionsMetaBoxes() {
		add_meta_box( $this->_slug . '-general-settings', __('General Settings', $this->_slug), array($this, 'generalSettingsMetaBox'), 'xavisys-' . $this->_slug, 'main');
		add_meta_box( $this->_slug . '-process-posts', __('Build Relations', $this->_slug), array($this, 'processPostsMetaBox'), 'xavisys-' . $this->_slug, 'main-2');
		if (get_option('erp-processedPosts')) {
			add_meta_box( $this->_slug . '-continue-processing-posts', __('Continue Processing Posts/Pages', $this->_slug), array($this, 'continueProcessingPostsMetaBox'), 'xavisys-' . $this->_slug, 'main-2');
		}
	}

	public function processPostsMetaBox() {
		?>
			<form action="" method="post">
				<p>
					<?php _e('Use this to build relationships for all posts.', $this->_slug); ?>
				</p>
				<p class="error"><?php _e('Warning, this could take a very long time (in test it took about 1 hour for 2000 posts).', $this->_slug); ?></p>
				<?php wp_nonce_field('erp-processPosts'); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<?php _e('Posts/Pages to process:', $this->_slug) ?>
						</th>
						<td>
							<input type="checkbox" name="erp[drafts]" value="true" id="erp-process-drafts" />
							<label for="erp-process-drafts"><?php _e('Process drafts', $this->_slug); ?></label><br />
							<input type="checkbox" name="erp[pending]" value="true" id="erp-process-pending" />
							<label for="erp-process-pending"><?php _e('Process pending posts', $this->_slug); ?></label><br />
							<input type="checkbox" name="erp[scheduled]" value="true" id="erp-process-scheduled" />
							<label for="erp-process-scheduled"><?php _e('Process scheduled posts', $this->_slug); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="process_posts" value="<?php esc_attr_e('Process Posts/Pages', $this->_slug); ?>" />
				</p>
			</form>
		<?php
	}

	public function continueProcessingPostsMetaBox() {
		?>
			<form action="" method="post">
				<p>
					<?php _e("The last processing didn't complete.  If you want to continue where it left off, use this:"); ?>
				</p>
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

	public function generalSettingsMetaBox() {
		?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="erp_title"><?php _e("Title:", $this->_slug); ?></label>
						</th>
						<td>
							<input id="erp_title" name="erp[title]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['erp']['title']); ?>" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_no_rp_text"><?php _e("Display Text When No Related Posts Found:", $this->_slug); ?></label>
						</th>
						<td>
							<input id="erp_no_rp_text" name="erp[no_rp_text]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['erp']['no_rp_text']); ?>" size="40" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_ignore_cats"><?php _e('Ignore Categories:', $this->_slug); ?></label>
						</th>
						<td id="categorydiv" class="categorydiv">
							<div id="categories-all" class="tabs-panel">
								<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">
<?php
							$erpWalker = new Walker_Category_Checklist_ERP();
							wp_category_checklist(0, 0, $this->_settings['erp']['ignore_cats'], array(), $erpWalker);
?>
								</ul>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_max_relations_stored"><?php _e('Max Related Posts to Store:', $this->_slug); ?></label>
						</th>
						<td>
							<input id="erp_max_relations_stored" name="erp[max_relations_stored]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['erp']['max_relations_stored']); ?>" size="40" />
							<span class="setting-description"><?php _e("Max number to store.  You can't display more than this.", $this->_slug); ?></span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="erp_num_to_display"><?php _e('Number of Related Posts to Display:', $this->_slug); ?></label>
						</th>
						<td>
							<input id="erp_num_to_display" name="erp[num_to_display]" type="text" class="regular-text code" value="<?php echo attribute_escape($this->_settings['erp']['num_to_display']); ?>" size="40" />
							<span class="setting-description"><?php _e('The number of related posts to display if none is specified.', $this->_slug); ?></span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php _e("Other Setting:", $this->_slug);?>
						</th>
						<td>
							<input name="erp[auto_insert]" id="erp_auto_insert_no" type="radio" value="no"<?php checked('no', $this->_settings['erp']['auto_insert']) ?>>
							<label for="erp_auto_insert_no">
								<?php _e("Do Not Auto Insert Into Posts", $this->_slug);?>
							</label>
							<br />
							<input name="erp[auto_insert]" id="erp_auto_insert_all" type="radio" value="all"<?php checked('all', $this->_settings['erp']['auto_insert']) ?>>
							<label for="erp_auto_insert_all">
								<?php _e("Auto Insert Everywhere (Posts and Pages)", $this->_slug);?>
							</label>
							<br />
							<input name="erp[auto_insert]" id="erp_auto_insert_single-all" type="radio" value="single-all"<?php checked('single-all', $this->_settings['erp']['auto_insert']) ?>>
							<label for="erp_auto_insert_single-all">
								<?php _e("Auto Insert Into Only Single Posts and Pages", $this->_slug);?>
							</label>
							<br />
							<input name="erp[auto_insert]" id="erp_auto_insert_posts" type="radio" value="posts"<?php checked('posts', $this->_settings['erp']['auto_insert']) ?>>
							<label for="erp_auto_insert_posts">
								<?php _e("Auto Insert Into Posts", $this->_slug);?>
							</label>
							<br />
							<input name="erp[auto_insert]" id="erp_auto_insert_single" type="radio" value="single"<?php checked('single', $this->_settings['erp']['auto_insert']) ?>>
							<label for="erp_auto_insert_single">
								<?php _e("Auto Insert Into Only Single Posts", $this->_slug);?>
							</label>
							<br />
							<br />
							<input name="erp[rss]" id="erp_rss" type="checkbox" value="yes"<?php checked('yes', $this->_settings['erp']['rss']) ?>>
							<label for="erp_rss">
								<?php _e("Related Posts for RSS", $this->_slug);?>
							</label>
						</td>
					</tr>
				</table>
		<?php
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

			$notice = sprintf(_n( 'Processed %d post.', 'Processed %d posts.', count($processed)), count($processed) );
			$notice .= '<br />';
			$notice .= sprintf(_n( 'Process took %s second.', 'Process took %s seconds.', $r), $r);
			$notice = str_replace( "'", "\'", "<div class='updated'><p>$notice</p></div>" );
			add_action('admin_notices', create_function( '', "echo '$notice';" ) );
		}
	}

	public function filterPostContent($content) {
		// We don't want to filter if this is a feed or if settings tell us not to
		if (
				(
					$this->_settings['erp']['auto_insert'] == 'all' ||
					( $this->_settings['erp']['auto_insert'] == 'posts' && !is_page() ) ||
					( $this->_settings['erp']['auto_insert'] == 'single-all' && is_singular() && !is_attachment() && !is_home() ) ||
					( $this->_settings['erp']['auto_insert'] == 'single' && is_single() )
				)
				&& !is_feed()
			) {
			$content .= $this->getRelatedPosts();
		}

		return $content;
	}

	public function filterPostContentRSS($content) {
		if ( $this->_settings['erp']['rss'] == 'yes' && is_feed() ) {
			$content .= $this->getRelatedPosts();
		}

		return $content;
	}

	/**
	 * @param [optional]$args Array of arguments containing any of the following:
	 * 	[num_to_display]	- Number of Posts to display
	 * 	[no_rp_text]		- Text to display if there are no related posts
	 * 	[title]				- Title for related posts list, empty for none
	 */
	public function getRelatedPosts( $args = array() ) {
		global $post;
		$output = '';

		$settings = wp_parse_args($args, $this->_settings['erp']);

		$relatedPosts = get_post_meta($post->ID, '_efficient_related_posts', true);

		if ( empty($relatedPosts) || $settings['num_to_display'] == 0 ){
			$output .= "<li>{$settings['no_rp_text']}</li>";
		} else {
			$relatedPosts = array_slice($relatedPosts, 0, $settings['num_to_display']);
			foreach ( $relatedPosts as $p ) {
				/**
				 * Handle IDs for backwards compat
				 */
				if ( ctype_digit($p) ) {
					$related_post = get_post($p);
					$p = array(
						'ID'			=> $related_post->ID,
						'post_title'	=> $related_post->post_title,
						'permalink'		=> get_permalink($related_post->ID)
					);
				}
				$link = "<a href='{$p['permalink']}' title='" . attribute_escape(wptexturize($p['post_title']))."'>".wptexturize($p['post_title']).'</a>';
				$output .= "<li>{$link}</li>";
			}
		}

		$output = "<ul class='related_post'>{$output}</ul>";

		if ( !empty($settings['title']) ) {
			$output = "<h3 class='related_post_title'>{$settings['title']}</h3>{$output}";
		}

		return $output;
	}


	/**
	 * @param [optional]$args See efficientRelatedPosts::getRelatedPosts
	 */
	public function relatedPosts( $args = array() ) {
		echo $this->getRelatedPosts($args);
	}

	private function _getPostIDs(&$p, $key) {
		$p = absint($p['ID']);
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
				array_walk($postIds, array($this, '_getPostIDs'));
				$postIds = array_diff(array_unique((array) $postIds), array('','0'));
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
				p.post_title,
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
					$overlap = array_intersect(wp_get_post_categories($related_post->ID), $this->_settings['erp']['ignore_cats']);

					$allRelatedPosts[] = $related_post;

					if ( empty($overlap) && count($relatedPostsToStore) < $this->_settings['erp']['max_relations_stored'] ) {
						$threshold = $related_post->matches;
						//unset($related_post->matches);
						$related_post->permalink = get_permalink($related_post->ID);
						$relatedPostsToStore[] = (array)$related_post;
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

					if ( empty($threshold) || $threshold <= $p->matches ) {
						// Get the current related posts
						$relatedPosts = get_post_meta($p->ID, '_efficient_related_posts', true);
						$relatedPosts[] = array('ID'=>$post->ID,'post_title'=>$post->post_title);
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

	public function fixPermalinks(){
		global $wpdb;

		$query = <<<QUERY
		SELECT * FROM `{$wpdb->postmeta}` WHERE `meta_key`='_efficient_related_posts'
QUERY;

		$relatedPostMeta = $wpdb->get_results($query);

		foreach ($relatedPostMeta as $relatedPosts) {
			$relatedPosts->meta_value = maybe_unserialize($relatedPosts->meta_value);
			foreach ($relatedPosts->meta_value as &$relatedPost) {
				$relatedPost['permalink'] = get_permalink($relatedPost['ID']);
			}

			$relatedPosts->meta_value = maybe_serialize( stripslashes_deep($relatedPosts->meta_value) );

			$data  = array( 'meta_value' => $relatedPosts->meta_value);
			$where = array(
				'meta_key'	=> $relatedPosts->meta_key,
				'post_id'	=> $relatedPosts->post_id
			);

			$wpdb->update( $wpdb->postmeta, $data, $where );
			wp_cache_delete($relatedPosts->post_id, 'post_meta');
		}
	}

	public function activate() {
		$this->processAllPosts();
	}

    /**
	 * Replace our shortCode with the list of related posts
	 *
	 * @param array $attr - array of attributes from the shortCode
	 * @param string $content - Content of the shortCode
	 * @return string - formatted XHTML replacement for the shortCode
	 */
    public function handleShortcodes($attr, $content = '') {
		if ( !empty($content) && empty($attr['title']) ) {
			$attr['title'] = $content;
		}
        $attr = shortcode_atts($this->_settings['erp'], $attr);
		return $this->getRelatedPosts($attr);
	}

	public function filterSettings($settings) {
		$defaults = array(
			'title'					=> __("Related Posts:", $this->_slug),
			'no_rp_text'			=> __("No Related Posts", $this->_slug),
			'ignore_cats'			=> array(),
			'max_relations_stored'	=> 10,
			'num_to_display'		=> 5,
			'auto_insert'			=> 'no',
			'rss'					=> 'no'
		);
		$settings = wp_parse_args($settings, $defaults);

		if ( !is_array($settings['ignore_cats']) ) {
			$settings['ignore_cats'] = preg_split('/\s*,\s*/', trim($settings['ignore_cats']), -1, PREG_SPLIT_NO_EMPTY);
		}
		$settings['max_relations_stored'] = intval($settings['max_relations_stored']);
		$settings['num_to_display'] = intval($settings['num_to_display']);

		return $settings;
	}
}
/**
 * Our custom Walker because Walker_Category_Checklist doesn't let you use your own field name
 */
class Walker_Category_Checklist_ERP extends Walker {
	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

	function start_lvl(&$output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl(&$output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el(&$output, $category, $depth, $args) {
		extract($args);

		$class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
		$output .= "\n<li id='category-$category->term_id'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="erp[ignore_cats][]" id="in-category-' . $category->term_id . '"' . (in_array( $category->term_id, $selected_cats ) ? ' checked="checked"' : "" ) . '/> ' . wp_specialchars( apply_filters('the_category', $category->name )) . '</label>';
	}

	function end_el(&$output, $category, $depth, $args) {
		$output .= "</li>\n";
	}
}


/**
 * Helper functions
 */

/**
 * @param [optional]$args See efficientRelatedPosts::getRelatedPosts
 */
function wp_related_posts( $args = array() ) {
	_deprecated_function( __FUNCTION__, '0.3.5', '"erp-show-related-posts" action' );
	// Instantiate our class
	$efficientRelatedPosts = efficientRelatedPosts::getInstance();
	$efficientRelatedPosts->relatedPosts($args);
}

/**
 * @param [optional]$args See efficientRelatedPosts::getRelatedPosts
 */
function wp_get_related_posts( $args = array() ) {
	_deprecated_function( __FUNCTION__, '0.3.5', '"erp-get-related-posts" filter' );
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
