<?php
/**
* EasyRP - main plugin class
*
* Use only one instance of it (pseudo-singleton), to keep your sanity.  
*/
class EasyRP extends WPPlugin
{
	const name = 'EasyRP';

	const prefix = 'easyrp_';
	const text_domain = 'easyrp';

	public $path;
	public $url;
	public $logfile_name;
	
	// array of taxonomy terms representing comment ratings criteria
	public $comment_rating_cats;


	/**
	 * __construct - set up basic class variables and add hooks
	 * 
	 * @param bool $standalone True if this is called in a standalone/ajax page.
	 */
	public function __construct($standalone = null)
	{
		parent::__construct();

		$this->path = dirname(__FILE__);
		$this->logfile_name = $this->path . '/log.html';
		$this->url = plugins_url('', __FILE__);

		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
		add_action('init', array($this, 'init'));
		
		
		// Uncomment these function and use them to add the admin pages and settings
		// add_action('admin_menu', array($this, 'add_admin_pages'));
		// add_action('admin_init', array($this, 'add_settings'));


		// update average and global ratings on review save
		add_filter('save_post', array($this, 'update_ratings'));


		// show ratings for Review
		add_filter('the_content', array($this, 'show_ratings'));


		// add rating controls to comments form

		// add comment rating fields - frontend
		add_action('comment_form', array($this, 'comment_form_rating_fields'));
		// add comment rating fields - backend
		add_action('add_meta_boxes_comment', array($this, 'add_rating_meta_boxes_comment'));

		// save comment rating fields - frontend
		add_action('pre_comment_on_post', array($this, 'allow_empty_comment'));
		add_action('comment_post', array($this, 'save_comment_rating_meta_data'));
		// TODO: do the verfication properly or just remove
// 		add_filter('preprocess_comment', array($this, 'verify_comment_rating_meta_data'));
		// save comment rating fields - backend
		add_action('edit_comment', array($this, 'edit_comment_save_ratings'));

		// show comment ratings
		add_filter('comment_text', array($this, 'show_comment_ratings'));
	}

	

	/**
	 * Enqueue scripts and styles, register content types, other init stuff
	 * 
	 * @action init
	 */
	public function init()
	{
		// javascript and css
		wp_enqueue_script('easyrp-raty',
			$this->url . '/assets/js/libs/raty/jquery.raty.js',
			array(),
			false,
			true
		);
		$stars_dir_theme_path = get_template_directory() . '/easyrp/stars';
		if (is_dir($stars_dir_theme_path)) {
			$stars_dir_url = get_template_directory_uri() . '/easyrp/stars';
		} else {
			$stars_dir_url = $this->url . '/assets/js/libs/raty/img';
		}
		wp_enqueue_script('easyrp-vars',
			$this->url . '/assets/js/easyrp-vars.js.php?&stars_dir_url=' . $stars_dir_url
		);
		wp_enqueue_script('easyrp-plugin',
			$this->url . '/assets/js/easyrp-plugin.js',
			array('easyrp-vars'),
			false,
			true
		);

		// register Reviews post type
		$this->register_review_post_type();
		
		// populate config
		require $this->path . '/config.php';
		$this->config = new stdClass();
		$this->config->rated_post_types = array();
		foreach ($easyrp_config as $content_type => $v) {
			if ($content_type != 'GENERAL') {
				$this->config->rated_post_types[$content_type] = new stdClass();
			}
		}
		
		// register rating taxonomies and
		// create default rating categories
		// (for separating user rating categories from editor rating categories
		// and common rating categories)
		foreach ($this->config->rated_post_types as $post_type => $v) {
			$this->register_rating_taxonomy($post_type);
			$taxonomy = $post_type . "_rating_category";
			if (!get_term_by('slug', 'general_ratings', $taxonomy)) {
				$this->create_default_rating_categories($taxonomy);
			}
		}
		
		foreach ($this->config->rated_post_types as $post_type => &$post_type_data) {
			$rating_cats = $this->get_rating_categories($post_type);
			$post_type_data->comment_rating_cats = array_merge(
				$rating_cats['general'],
				$rating_cats['users']
			);
			$post_type_data->editor_rating_cats = array_merge(
					$rating_cats['general'],
					$rating_cats['editors']
			);
		}
		
		// TODO:
		// 1. foreach $this->config->rated_post_types
		// 2. foreach $this->comment_rating_cats and $this->editor_rating_cats

// 		// get rating categories to be used by other methods
// 		$rating_cats = $this->get_rating_categories();
// 		$this->comment_rating_cats = array_merge(
// 			$rating_cats['general'],
// 			$rating_cats['users']
// 		);
// 		$this->editor_rating_cats = array_merge(
// 			$rating_cats['general'],
// 			$rating_cats['editors']
// 		);
	}



	// these methods enable rating by comments:

	
	/**
	 * add comment rating fields - frontend
	 * 
	 * @action comment_form
	 */
	public function comment_form_rating_fields()
	{
		global $post;
		
		if (isset($this->config->rated_post_types[$post->post_type])) {
			foreach ($this->config->rated_post_types[$post->post_type]->comment_rating_cats as $cat) {
				echo $this->comment_rating_ctrl($cat);
			}
		}
	}

	
	/**
	 * add comment rating fields - backend
	 * 
	 * @action add_meta_boxes_comment
	 */
	public function add_rating_meta_boxes_comment()
	{
		add_meta_box(
			'comment_ratings',
			__('Ratings', self::text_domain),
			array($this, 'add_comment_ratings_meta_box'),
			'comment',
			'normal',
			'high'
		);
	}

	/**
	 * meta box callback for backend comment rating fields
	 * 
	 * @param object $comment
	 */
	public function add_comment_ratings_meta_box($comment)
	{
		// only show comment ratings meta box for comments belonging to a rated post type
		$post_type = get_post_type($comment->comment_post_ID);
		if (!isset($this->config->rated_post_types[$post_type])) return;
		
		foreach ($this->config->rated_post_types[$post_type]->comment_rating_cats as $cat) {
			$rating = get_comment_meta(
					$comment->comment_ID,
					'rating_' . $cat->slug,
					true
			);
			echo $this->comment_rating_ctrl($cat, (int) $rating);
		}
	}

	
	// these methods handle saving comment rating fields (frontend):
	
	/**
	 * allow comments that act only as ratings (no content)
	 * and prevent duplicate comment errors by adding some
	 * unique dummy comment content.
	 * 
	 * @action pre_comment_on_post
	 */
	public function allow_empty_comment()
	{
		// make 'comment' field optional to enable rating only comments
		if (empty($_POST['comment'])) {
			$_POST['comment'] = "<span style='display: none'>{no comment " . uniqid() . "}</span>";
		}
	}

	/**
	 * save comment rating fields - frontend
	 * 
	 * @param int $comment_id
	 * @param bool $save_empty
	 * 
	 * @action comment_post
	 */
	public function save_comment_rating_meta_data($comment_id, $save_empty = false)
	{		
		// only do this for comments belonging to a rated post type
		$comment = get_comment($comment_id);
		$post_type = get_post_type($comment->comment_post_ID);
		if (!isset($this->config->rated_post_types[$post_type])) return;
		
		foreach ($this->config->rated_post_types[$post_type]->comment_rating_cats as $cat) {
			$meta_key = 'rating_' . $cat->slug;
			if ($save_empty || !empty($_POST[$meta_key])) {
				$meta_val = wp_filter_nohtml_kses($_POST[$meta_key]);
				update_comment_meta($comment_id, $meta_key, $meta_val);
			}
		}

		// save when this was computed
		update_post_meta($comment->comment_post_ID, 'last_rated_when', date('Y-m-d H:i:s'));
		update_post_meta($comment->comment_post_ID, 'last_rated_by', $comment_id);

		$this->update_average_ratings($comment->comment_post_ID);
	}

	/**
	 * VERY basic sanity checks when saving comment ratings via frontend
	 * 
	 * @param array $comment_data
	 * @return array
	 * 
	 * @filter preprocess_comment
	 */
	// TODO: do this properly or remove it...
// 	public function verify_comment_rating_meta_data($comment_data)
// 	{
// 		foreach ($this->comment_rating_cats as $cat) {
// 			$meta_key = 'rating_' . $cat->slug;
// 			if (!empty($_POST[$meta_key]) &&
// 				(!is_numeric($_POST[$meta_key]) ||
// 					$_POST[$meta_key] < 0.5 ||
// 					$_POST[$meta_key] > 5)) {
// 				wp_die(__( 'Error: Bad rating value. Hit the Back button on your Web browser and resubmit your comment and rating.', self::text_domain));
// 			}
// 		}

// 		return $comment_data;
// 	}

	
	/**
	 * save comment rating fields - backend
	 * 
	 * @param int $comment_id
	 * 
	 * @action edit_comment
	 */
	public function edit_comment_save_ratings($comment_id)
	{
		if (isset($_POST['extend_comment_update_wpnonce']) &&
			wp_verify_nonce($_POST['extend_comment_update_wpnonce'], 'extend_comment_update')) {

			$this->save_comment_rating_meta_data($comment_id, true);
		}
	}

	/**
	 * show comment ratings
	 * 
	 * @param string $text
	 * @return string
	 * 
	 * @filter comment_text
	 */
	public function show_comment_ratings($text)
	{
		$comment_id = get_comment_ID();
		$comment = get_comment($comment_id);
		
		// only for rated post types
		if (!isset($this->config->rated_post_types[get_post_type($comment->comment_post_ID)])) {
			return $text;
		}

		ob_start();
		require $this->frontend_template_path('comment_ratings_display.php');
		$ratings_html = ob_get_clean();

		return $text . $ratings_html;
	}



	/**
	 * update average and global ratings on review save
	 * 
	 * @param int $post_id
	 * 
	 * @action save_post
	 */
	public function update_ratings($post_id)
	{
		// only for rated post types
		if (isset($this->config->rated_post_types[get_post_type($post_id)])) {
			$this->init_post_global_rating($post_id);
			$this->update_average_ratings($post_id);			
		}
	}



	/**
	 * Show ratings
	 * 
	 * @param string $content
	 * @return string
	 * 
	 * @filter the_content
	 */
	public function show_ratings($content)
	{
		global $post;

// 		d($content); // DEBUG

		// only show ratings for rated content types
		if (array_key_exists($post->post_type, $this->config->rated_post_types)) {
			
			ob_start();
			require $this->frontend_template_path('ratings_display.php');
			$ratings_html = ob_get_clean();
			
			$content = $ratings_html . $content;
		}
		
// 		d($content); // DEBUG
		
		return $content;
	}
	
	
	
	// these methods make up an API to be used by themes or plugins:

	
	/**
	 * returns the html for a rating control
	 * 
	 * @param unknown $rating
	 * @param string $read_only
	 * @param string $cancel
	 * @param string $score_name
	 * @param string $target
	 * @param string $halves
	 * @param string $stars_path
	 * @param string $star_size
	 * @return string
	 * 
	 * TODO: properly document this
	 */
	public function rating_ctrl($rating,
								$read_only = null,
								$cancel = null,
								$score_name = null,
								$target = null,
								$halves = null,
								$stars_path = null,
								$star_size = null)
	{
		// optionally accept keyword arguments (as one array)
		if (is_array($rating) && func_num_args() == 1) {
			$allowed_kw_params = array_intersect_key($rating, array(
				'rating' => 1,
				'read_only' => 1,
				'cancel' => 1,
				'score_name' => 1,
				'target' => 1,
				'halves' => 1,
				'stars_path' => 1,
				'star_size' => 1,
			));
			$args = array_merge(array(
				'read_only' => $read_only,
				'cancel' => $cancel,
			), $allowed_kw_params);
			extract($args);
		}

		return "<div class='rating-ctrl'" .
				(!is_array($rating) ? " data-rating='$rating'" : '') .
				(!empty($read_only) ? ' data-read-only' : '') .
				(!empty($cancel) ? ' data-cancel' : '') .
				(!empty($score_name) ? " data-score-name='$score_name'" : '') .
				(!empty($target) ? " data-target='$target'" : '') .
				(!empty($halves) ? ' data-halves' : '') .
				(!empty($stars_path) ?
					" data-stars-path='$stars_path'" : "") .
				(!empty($star_size) ? " data-star-size='$star_size'" : '') .
			"></div>";
	}

	
	/**
	 * get rank (in a category/taxonomy or overall)
	 * 
	 * @param int $post_id
	 * @param object $term
	 * 
	 * TODO: properly document this
	 */
	public function get_rank($post_id, $term = null)
	{
		global $wpdb, $table_prefix;
		
		$post_type = get_post_type($post_id);

		if ($term !== null) {			
			
			$taxonomy = $post_type .'_review_category';
			
			$terms_in_param = '(';
			
			// get array of term and subterm ids
			$subterms = get_terms($taxonomy, array(
				'parent' => $term->term_id,
			));
			$term_taxonomy_ids = array($term->term_taxonomy_id);
			if ($subterms) foreach ($subterms as $subterm) {
				if ($subterm->slug[0] != '_') $term_taxonomy_ids[] = $subterm->term_taxonomy_id;
			}

			// create terms placeholder string
			$term_placeholders = array();
			for ($i = 0; $i < count($term_taxonomy_ids); $i++) $term_placeholders[] = '%d';
			$terms_in_param .= implode(',', $term_placeholders) . ')';
		}

		$sql = array(
			0 =>
			"
			select distinct
				(
					select count(distinct pi.id)
					from
						{$table_prefix}posts pi
						left join {$table_prefix}postmeta mi on mi.post_id = pi.ID and mi.meta_key = 'global_average_rating_overall'
			",
			1 =>
			"			join {$table_prefix}term_relationships tri on pi.ID = tri.object_id and
							tri.term_taxonomy_id in $terms_in_param",
			2 =>
			"		where
						pi.post_type = {$post_type} and
						pi.post_status = 'publish' and
						mi.meta_value >= m_garo.meta_value
				) as rank
			from
				{$table_prefix}posts p
				left join {$table_prefix}postmeta m_garo on m_garo.post_id = p.ID and m_garo.meta_key = 'global_average_rating_overall'
			",
			3 =>
			"	join {$table_prefix}term_relationships tr on p.ID = tr.object_id and
					tr.term_taxonomy_id in $terms_in_param",
			4 =>
			"
			where
				p.ID = %d;
			"
		);

		if ($term === null)
		{
			$sql = $sql[0] . $sql[2] . $sql[4];
			$r = $wpdb->get_var($wpdb->prepare($sql, $post_id));
		} else {
			$sql = implode('', $sql);
			$params = array_merge(
				$term_taxonomy_ids,
				$term_taxonomy_ids,
				array($post_id)
			);
			$r = $wpdb->get_var($wpdb->prepare($sql, $params));
		}

		return $r;

		// TODO: find out why this does not work (see related SO question):
		// $sql = array(
		// 	0 =>
		// 	"select	rank
		// 	from
		// 		(select
		// 			p.id as post_id,
		// 			m.meta_value as global_average_rating_overall,
		// 			@rownum := @rownum + 1 as rank
		// 		from
		// 			(select @rownum := 0) rn,
		// 			{$table_prefix}posts p
		// 			left join {$table_prefix}postmeta m on m.post_id = p.ID and m.meta_key = 'global_average_rating_overall'
		// 	",
		// 	1 =>
		// 	"		join {$table_prefix}term_relationships tr on p.ID = tr.object_id\n",
		// 	2 =>
		// 	"	where\n",
		// 	3 =>
		// 	"		tr.term_taxonomy_id = %d and\n",
		// 	4 =>
		// 	"		p.post_status = 'publish'
		// 		group by p.id
		// 		order by global_average_rating_overall desc
		// 		) as result
		// 	where post_id = %d;
		// 	"
		// );

		// if ($term_taxonomy_id === null)
		// {
		// 	$sql = $sql[0] . $sql[2] . $sql[4];
		// 	$r = $wpdb->get_var($wpdb->prepare($sql, $post_id));
		// } else {
		// 	$sql = implode('', $sql);
		// 	$r = $wpdb->get_var($wpdb->prepare($sql, $term_taxonomy_id, $post_id));
		// }
	}

	
	/**
	 * get top rated
	 * 
	 * @param number $count
	 * @param string $term
	 * @param array $extra_sql
	 * @return array
	 * 
	 * TODO: properly document this
	 */
	public function top_rated($post_type, $count, $term = null, $extra_sql = array())
	{
		// sanity check:
		// ensure $post_type is a rated post type
		assert('isset($this->config->rated_post_types[$post_type])');
		
		global $wpdb, $table_prefix;

		$terms_in_param = '(';

		if ($term !== null) {
			if (is_object($term)) {
				// get array of term and subterm ids
				$subterms = get_terms($term->taxonomy, array(
					'parent' => $term->term_id,
				));
				$term_taxonomy_ids = array($term->term_taxonomy_id);
				if ($subterms) foreach ($subterms as $subterm) {
					if ($subterm->slug[0] != '_') $term_taxonomy_ids[] = $subterm->term_taxonomy_id;
				}
	
				// create terms placeholder string
				$term_placeholders = array();
				for ($i = 0; $i < count($term_taxonomy_ids); $i++) $term_placeholders[] = '%d';
				$terms_in_param .= implode(',', $term_placeholders) . ')';
			} else {
				$term_taxonomy_ids = array($term);
				$terms_in_param = "(%d)";
			}
		}

		$sql = array(
			0 => "
			select
				p.ID,
				p.post_title,
				m_garo.meta_value as global_average_rating_overall,
				m_lrb.meta_value as last_rated_by,
				m_ero.meta_value as editor_rating_overall,
				m_aro.meta_value as average_rating_overall,
				cm.meta_value as last_user_rating_overall,
				(
					select count(distinct pi.id)
					from
						{$table_prefix}posts pi
						left join {$table_prefix}postmeta mi on mi.post_id = pi.ID and mi.meta_key = 'global_average_rating_overall'",

						1 => "
						join {$table_prefix}term_relationships tri on pi.ID = tri.object_id and
							tri.term_taxonomy_id in $terms_in_param",

					2 => "
					where
						pi.post_type = '$post_type' and
						pi.post_status = 'publish' and
						mi.meta_value >= m_garo.meta_value
				) as rank,
				(
					select count(*)
					from
						{$table_prefix}comments
					where
						comment_post_ID = p.ID
				) as comments_no
			from
				{$table_prefix}posts p
				left join {$table_prefix}postmeta m_garo on m_garo.post_id = p.ID and m_garo.meta_key = 'global_average_rating_overall'
				left join {$table_prefix}postmeta m_ero on m_ero.post_id = p.ID and m_ero.meta_key = 'editor_rating_overall'
				left join {$table_prefix}postmeta m_lrb on m_lrb.post_id = p.ID and m_lrb.meta_key = 'last_rated_by'
				left join {$table_prefix}postmeta m_aro on m_aro.post_id = p.ID and m_aro.meta_key = 'average_rating_overall'
				left join {$table_prefix}comments c on c.comment_ID = m_lrb.meta_value
				left join {$table_prefix}commentmeta cm on cm.comment_id = c.comment_ID and cm.meta_key = 'rating_overall'",

				3 => "
				join {$table_prefix}term_relationships tr on p.ID = tr.object_id and
					tr.term_taxonomy_id in $terms_in_param",

			4 => "
			where " .
				(isset($extra_sql['! where']) ?
					$extra_sql['! where'] :
					("
					p.post_type = '$post_type' and
					p.post_status = 'publish'" .
					(isset($extra_sql['where']) ?
						' and ' . $extra_sql['where'] : '')
					)) .
			"
			group by p.ID
			order by " .
				(isset($extra_sql['! order by']) ?
					$extra_sql['! order by'] :
					("
					global_average_rating_overall desc" .
					(isset($extra_sql['order by']) ?
						", " . $extra_sql['order by'] : '')
					)) .
			"
			limit %d;"
		);

		if ($term === null)
		{
			$sql = $sql[0] . $sql[2] . $sql[4];

// 			d($sql); // DEBUG

			$r = $wpdb->get_results($wpdb->prepare($sql, $count));
		} else {
			$sql = implode('', $sql);

// 			d($sql); // DEBUG

			$params = array_merge(
				$term_taxonomy_ids,
				$term_taxonomy_ids,
				array($count)
			);
			$r = $wpdb->get_results($wpdb->prepare($sql, $params));
		}

		return $r;
	}

	
	/**
	 * get latest reviews
	 * 
	 * @param number $count
	 * @param int $term_taxonomy_id
	 * @return array
	 * 
	 * TODO: properly document this
	 */
	public function latest($post_type, $count, $term = null)
	{
		// sanity check:
		// ensure $post_type is a rated post type
		assert('isset($this->config->rated_post_types[$post_type])');
		
		global $wpdb, $table_prefix;

		$sql = array(
			0 =>
			"
			select
				ID,
				post_title,
				m_lrw.meta_value as last_rated_when,
				m_luro.meta_value as last_rated_by,
				m_ero.meta_value as editor_rating_overall,
				cm.meta_value as last_user_rating_overall
			from {$table_prefix}posts p
			",
			1 =>
			"	join {$table_prefix}term_relationships tr on p.ID = tr.object_id and tr.term_taxonomy_id = %d\n",
			2 =>
			"
				left join {$table_prefix}postmeta m_lrw on p.ID = m_lrw.post_id and m_lrw.meta_key = 'last_rated_when'
				left join {$table_prefix}postmeta m_luro on p.ID = m_luro.post_id and m_luro.meta_key = 'last_rated_by'
				left join {$table_prefix}postmeta m_ero on p.ID = m_ero.post_id and m_ero.meta_key = 'editor_rating_overall'
				left join {$table_prefix}comments c on c.comment_ID = m_luro.meta_value
				left join {$table_prefix}commentmeta cm on c.comment_ID = cm.comment_id and cm.meta_key = 'rating_overall'
			where post_type = '$post_type' and post_status = 'publish'
			order by m_lrw.meta_key desc, post_modified_gmt desc
			limit %d;
			",
		);

		if ($term === null) {
			$sql = $sql[0] . $sql[2];

			return $wpdb->get_results($wpdb->prepare($sql, $count));
		} else {
			$sql = implode('', $sql);			
			$term_taxonomy_id = is_object($term) ?
								$term->term_taxonomy_id :
								$term;
			return $wpdb->get_results($wpdb->prepare($sql, $term_taxonomy_id, $count));
		}
	}



	// activate/deactivate plugin:

	public function activate()
	{
		require_once $this->path . '/activate.php';
	}

	public function deactivate()
	{
		require_once $this->path . '/deactivate.php';
	}

	

	// utility methods:

	
	/**
	 * register Review custom post type and related taxonomies
	 */
	private function register_review_post_type()
	{
		register_post_type(self::prefix . 'review', array(
			'labels' => array(
				'name' => __('Reviews', self::text_domain),
				'singular_name' => __('Review', self::text_domain)
			),
			'public' => true,
			'has_archive' => true,
			'menu_position' => 5,
			'supports' => array(
				'title',
				'editor',
				'author',
				'comments',
				'thumbnail',
				'excerpt',
				'revisions',
				'custom-fields'
			),
			'taxonomies' => array(
				self::prefix . 'review_category',
				self::prefix . 'review_tag',
			),
		));

		register_taxonomy(
			self::prefix . 'review_category',
			self::prefix . 'review',
			array(
				'labels' => array(
					'name'                => _x('Categories', 'taxonomy general name', self::text_domain),
					'singular_name'       => _x('Category', 'taxonomy singular name', self::text_domain),
					'search_items'        => __('Search Categories', self::text_domain),
					'all_items'           => __('All Categories', self::text_domain),
					'parent_item'         => __('Parent Category', self::text_domain),
					'parent_item_colon'   => __('Parent Category:', self::text_domain),
					'edit_item'           => __('Edit Category', self::text_domain),
					'update_item'         => __('Update Category', self::text_domain),
					'add_new_item'        => __('Add New Category', self::text_domain),
					'new_item_name'       => __('New Category Name', self::text_domain),
					'menu_name'           => __('Categories', self::text_domain),
				),
				'hierarchical'        => true,
				'show_ui'             => true,
				'show_admin_column'   => true,
				'query_var'           => true,
				'rewrite'             => array('slug' => self::prefix . 'review_category'),
			)
		);

		register_taxonomy(
			self::prefix . 'review_tag',
			self::prefix . 'review',
			array(
				'labels' => array(
					'name'                => _x('Tags', 'taxonomy general name', self::text_domain),
					'singular_name'       => _x('Tag', 'taxonomy singular name', self::text_domain),
					'search_items'        => __('Search Tags', self::text_domain),
					'all_items'           => __('All Tags', self::text_domain),
					'edit_item'           => __('Edit Tag', self::text_domain),
					'update_item'         => __('Update Tag', self::text_domain),
					'add_new_item'        => __('Add New Tag', self::text_domain),
					'new_item_name'       => __('New Tag Name', self::text_domain),
					'menu_name'           => __('Tags', self::text_domain),
				),
				'hierarchical'        => false,
				'show_ui'             => true,
				'show_admin_column'   => true,
				'query_var'           => true,
				'rewrite'             => array('slug' => self::prefix . 'review_tag'),
			)
		);
	}
	
	
	/**
	 * register a ratings taxonomy for a post type
	 * 
	 * @param string $post_type
	 */
	private function register_rating_taxonomy($post_type)
	{
		register_taxonomy(
			$post_type . '_rating_category',
			$post_type,
			array(
				'labels' => array(
				'name'                => _x('Rating Categories', 'taxonomy general name', self::text_domain),
				'singular_name'       => _x('Rating Category', 'taxonomy singular name', self::text_domain),
				'search_items'        => __('Search Rating Categories', self::text_domain),
				'all_items'           => __('All Rating Categories', self::text_domain),
				'parent_item'         => __('Parent Rating Category', self::text_domain),
				'parent_item_colon'   => __('Parent Rating Category:', self::text_domain),
				'edit_item'           => __('Edit Rating Category', self::text_domain),
				'update_item'         => __('Update Rating Category', self::text_domain),
				'add_new_item'        => __('Add New Rating Category', self::text_domain),
				'new_item_name'       => __('New Rating Category Name', self::text_domain),
				'menu_name'           => __('Rating Categories', self::text_domain),
			),
			'hierarchical'        => true,
			'show_ui'             => true,
			'show_admin_column'   => true,
			'query_var'           => true,
			'rewrite'             => array('slug' => $post_type . '_rating_category'),
			)
		);
	
// 		register_taxonomy_for_object_type(
// 			$post_type . '_rating_category',
// 			$post_type
// 		);
	}

	
	/**
	 * create default rating reviews categories
	 * (for separating user rating categories from editor rating categories
     * and common rating categories)
	 */
	private function create_default_rating_categories($taxonomy)
	{
		$r = wp_insert_term(
			"General Ratings",
			$taxonomy,
			array(
				'slug' => 'general_ratings',
			)
		);

		wp_insert_term(
			"Overall",
			$taxonomy,
			array(
				'slug' => 'overall',
				'parent' => $r['term_id'],
			)
		);

		wp_insert_term(
			"Editors' Ratings",
			$taxonomy,
			array(
				'slug' => 'editors_ratings',
			)
		);

		wp_insert_term(
			"Users' Ratings",
			$taxonomy,
			array(
				'slug' => 'users_ratings',
			)
		);
	}

	
	/**
	 * get rating categories to be used by other methods
	 * 
	 * @return array
	 */
	private function get_rating_categories($post_type)
	{
		$cats = get_terms($post_type . '_rating_category', array(
			'hide_empty' => false,
			'orderby' => 'slug',
		));
// 		d($cats);

		// get the ids for parent categories used for rating grouping
		$rating_categories_groups_ids = array();
		foreach ($cats as $k => $cat) {
			switch ($cat->slug) {
				case 'general_ratings':
				case 'editors_ratings':
				case 'users_ratings':
					$rating_categories_groups_ids[substr($cat->slug, 0, -8)] = $cat->term_id;
					unset($cats[$k]);

			}
			if (count($rating_categories_groups_ids) >= 3) break;
		}
// 		d($rating_categories_groups_ids);

		// group rating categories
		$grouped_cats = array(
			'general' => array(),
			'editors' => array(),
			'users' => array(),
		);
		foreach ($cats as $cat) {
			switch ($cat->parent) {
				case $rating_categories_groups_ids['general']:
					$grouped_cats['general'][] = $cat;
					break;
				case $rating_categories_groups_ids['editors']:
					$grouped_cats['editors'][] = $cat;
					break;
				case $rating_categories_groups_ids['users']:
					$grouped_cats['users'][] = $cat;
			}
		}

		return $grouped_cats;
	}

	
	/**
	 * return the html for the rating widget representing the
	 * $cat rating criteria
	 * 
	 * @param object $cat taxonomy term representing the rating criteria
	 * @param int $rating
	 * @return string
	 */
	private function comment_rating_ctrl($cat, $rating = null)
	{
		ob_start();
		require $this->frontend_template_path('comment_rating_ctrl.php');
		return ob_get_clean();
	}

	
	/**
	 * update average ratings after each new user rating
	 * 
	 * @param unknown $post_id
	 */
	private function update_average_ratings($post_id)
	{
		// sanity check:
		// make sure the post belong to a rated post_type
		assert('isset($this->config->rated_post_types[get_post_type($post_id)])');
		
		$comments = get_comments(array(
			'post_id' => $post_id,
		));
		$authors = array();
		$ratings_to_average = array();
		$comment_rating_cats = $this->config->rated_post_types[get_post_type($post_id)]->comment_rating_cats;

		foreach ($comments as $comment) {
			
			if (isset($authors[$comment->comment_author_email])) continue;
			$authors[$comment->comment_author_email] = true;

			foreach ($comment_rating_cats as $cat) {
				$meta_key = 'rating_' . $cat->slug;
				$meta_val = get_comment_meta($comment->comment_ID, $meta_key, true);
				if ($meta_val) {
					if (!isset($ratings_to_average[$meta_key])) $ratings_to_average[$meta_key] = array();
					$ratings_to_average[$meta_key][] = $meta_val;
				}
			}
		}

		foreach ($ratings_to_average as $k => $ra) {

			if (count($ra)) {
				$average = array_sum($ra) / count($ra);
				update_post_meta($post_id, 'average_' . $k, $average);
			} else {
				$average = null;
			}

			// calculate a global average only for ratings that have a corresponding editor rating.
			// global average is (editor + average) / 2
			if ($editor_rating = get_post_meta($post_id, 'editor_' . $k, true)) {
				
				if ($average === null) $global_average = $editor_rating;
				else $global_average = ($editor_rating + $average) / 2;				
				update_post_meta($post_id, 'global_average_' . $k, $global_average);
			}
		}
	}
	
	/**
	 * Initialize global rating for post
	 *
	 * @param int $post_id
	 */
	private function init_post_global_rating($post_id)
	{
		if (!get_post_meta($post_id, 'global_average_rating_overall', true)) {
	
			if (isset($_POST['meta']) && is_array($_POST['meta'])) {
				foreach ($_POST['meta'] as $kv) {
					if ($kv['key'] == 'editor_rating_overall') {
						update_post_meta($post_id, 'global_average_rating_overall', $kv['value']);
						update_post_meta($post_id, 'last_rated_when', date('Y-m-d H:i:s'));
						update_post_meta($post_id, 'last_rated_by', 'editor');
						break;
					}
				}
			} elseif (isset($_POST['metakeyselect']) && $_POST['metakeyselect'] == 'editor_rating_overall') {
				update_post_meta($post_id, 'global_average_rating_overall', $_POST['metavalue']);
				update_post_meta($post_id, 'last_rated_when', date('Y-m-d H:i:s'));
				update_post_meta($post_id, 'last_rated_by', 'editor');
			}
		}
	}
}
