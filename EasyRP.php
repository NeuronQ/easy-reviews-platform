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


	/**
	 * __construct
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
		// add_action('admin_menu', array($this, 'add_admin_pages'));
		// add_action('admin_init', array($this, 'add_settings'));


		// initialize global rating for newly created posts
		add_filter('save_post', array($this, 'init_post_global_rating'));


		// show ratings
		add_filter('the_content', array($this, 'show_ratings'));


		// add rating controls to comments form

		// comment rating fields - frontend
		add_action('comment_form', array($this, 'comment_form_rating_fields_after'));
		// comment rating fields - backend
		add_action('add_meta_boxes_comment', array($this, 'add_rating_meta_boxes_comment'));

		// save comment rating fields - frontend
		add_action('pre_comment_on_post', array($this, 'allow_empty_comment'));
		add_action('comment_post', array($this, 'save_comment_rating_meta_data'));
		add_filter('preprocess_comment', array($this, 'verify_comment_rating_meta_data'));
		// save comment rating fields - backed
		add_action('edit_comment', array($this, 'edit_comment_save_ratings'));

		// show comment ratings - frontend
		add_filter('comment_text', array($this, 'show_comment_ratings'));
	}



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
			$stars_dir_path = get_template_directory_uri() . '/easyrp/stars';
		} else {
			$stars_dir_path = $this->url . '/assets/js/libs/raty/img';
		}
		wp_enqueue_script('easyrp-plugin',
			$this->url . '/assets/js/easyrp-plugin.js.php?&stars_dir_path=' . $stars_dir_path,
			array(),
			false,
			true
		);

		// register Reviews post type
		$this->register_review_post_type();

		// create default rating reviews categories
		// (for separating user rating categories from editor rating categories
		// and common rating categories)
		if (!get_term_by('slug', 'general_ratings', self::prefix . 'rating_category')) {			
			$this->create_default_review_rating_categories();
		}

		// get rating categories to be used by other methods
		$rating_cats = $this->get_rating_categories();
		$this->comment_rating_cats = array_merge(
			$rating_cats['general'],
			$rating_cats['users']
		);
		$this->editor_rating_cats = array_merge(
			$rating_cats['general'],
			$rating_cats['editors']
		);
	}



	// comments

	public function comment_form_rating_fields_after()
	{
		global $post;

		// only show comment form rating controls for reviews
		if ($post->post_type != 'easyrp_review') return;

		foreach ($this->comment_rating_cats as $cat) {
			echo $this->comment_rating_widget($cat);
		}
	}

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

	public function add_comment_ratings_meta_box($comment)
	{
		// only show comment ratings meta box for reviews
		if (get_post_type($comment->comment_post_ID) != 'easyrp_review') return;

		wp_nonce_field(
			'extend_comment_update',
			'extend_comment_update_wpnonce',
			false
		);

		foreach ($this->comment_rating_cats as $cat) {
			$rating = get_comment_meta(
				$comment->comment_ID,
				'rating_' . $cat->slug,
				true
			);
			echo $this->comment_rating_widget($cat, (int) $rating);
		}
	}

	public function allow_empty_comment()
	{
		// make 'comment' field optional to enable rating only comments
		if (empty($_POST['comment'])) $_POST['comment'] = '{no comment ' . uniqid() . '}';
	}

	public function save_comment_rating_meta_data($comment_id, $save_empty = false)
	{
		foreach ($this->comment_rating_cats as $cat) {
			$meta_key = 'rating_' . $cat->slug;
			if ($save_empty || !empty($_POST[$meta_key])) {
				$meta_val = wp_filter_nohtml_kses($_POST[$meta_key]);
				update_comment_meta($comment_id, $meta_key, $meta_val);
			}
		}

		$comment = get_comment($comment_id);

		// save when this was computed
		update_post_meta($comment->comment_post_ID, 'last_rated_when', date('Y-m-d H:i:s'));
		update_post_meta($comment->comment_post_ID, 'last_rated_by', $comment_id);

		$this->update_average_ratings($comment->comment_post_ID);
	}

	public function verify_comment_rating_meta_data($comment_data)
	{
		foreach ($this->comment_rating_cats as $cat) {
			$meta_key = 'rating_' . $cat->slug;
			if (!empty($_POST[$meta_key]) &&
				(!is_numeric($_POST[$meta_key]) ||
					$_POST[$meta_key] < 0.5 ||
					$_POST[$meta_key] > 5)) {
				wp_die(__( 'Error: Bad rating value. Hit the Back button on your Web browser and resubmit your comment and rating.', self::text_domain));
			}
		}

		return $comment_data;
	}

	public function edit_comment_save_ratings($comment_id)
	{
		if (isset($_POST['extend_comment_update_wpnonce']) &&
			wp_verify_nonce($_POST['extend_comment_update_wpnonce'], 'extend_comment_update')) {

			$this->save_comment_rating_meta_data($comment_id, true);
		}
	}

	public function show_comment_ratings($text)
	{
		$comment_id = get_comment_ID();
		$comment = get_comment($comment_id);
		
		// only show for reviews
		if (get_post_type($comment->comment_post_ID) != 'easyrp_review') return $text;

		ob_start();
		require $this->frontend_template_path('comment_ratings_display.php');
		$ratings_html = ob_get_clean();

		return $text . $ratings_html;
	}



	// init post global ratings
	public function init_post_global_rating($post_id)
	{
		if (get_post_type($post_id) == 'easyrp_review' &&
			!get_post_meta($post_id, 'global_average_rating_overall', true)) {
			
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
			
			// recalculate all average ratings after deleting the global rating
			$this->update_average_ratings($post_id);
		}
	}



	// API functions - to be used by plugins/widgets

	// show ratings
	public function show_ratings($content)
	{
		global $post;

		// d($content); // DEBUG

		// only show ratings for reviews
		if ($post->post_type != 'easyrp_review') return $content;

		ob_start();
		require $this->frontend_template_path('ratings_display.php');
		$ratings_html = ob_get_clean();

		$content = $ratings_html . $content;

		// d($content); // DEBUG

		return $content;
	}

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

	// get review rank (in a category/taxonomy or overall)
	public function get_rank($post_id, $term = null)
	{
		global $wpdb, $table_prefix;

		$terms_in_param = '(';

		if ($term !== null) {
			// get array of term and subterm ids
			$subterms = get_terms('easyrp_review_category', array(
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
						wp_posts pi
						left join wp_postmeta mi on mi.post_id = pi.ID and mi.meta_key = 'global_average_rating_overall'
			",
			1 =>
			"			join wp_term_relationships tri on pi.ID = tri.object_id and
							tri.term_taxonomy_id in $terms_in_param",
			2 =>
			"		where
						pi.post_type = 'easyrp_review' and
						pi.post_status = 'publish' and
						mi.meta_value >= m_garo.meta_value
				) as rank
			from
				wp_posts p
				left join wp_postmeta m_garo on m_garo.post_id = p.ID and m_garo.meta_key = 'global_average_rating_overall'
			",
			3 =>
			"	join wp_term_relationships tr on p.ID = tr.object_id and
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

	public function top_rated_reviews($count = 4, $term = null, $extra_sql = array())
	{
		global $wpdb, $table_prefix;

		$terms_in_param = '(';

		if ($term !== null) {
			// get array of term and subterm ids
			$subterms = get_terms('easyrp_review_category', array(
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
						wp_posts pi
						left join wp_postmeta mi on mi.post_id = pi.ID and mi.meta_key = 'global_average_rating_overall'",

						1 => "
						join wp_term_relationships tri on pi.ID = tri.object_id and
							tri.term_taxonomy_id in $terms_in_param",

					2 => "
					where
						pi.post_type = 'easyrp_review' and
						pi.post_status = 'publish' and
						mi.meta_value >= m_garo.meta_value
				) as rank,
				(
					select count(*)
					from
						wp_comments
					where
						comment_post_ID = p.ID
				) as comments_no
			from
				wp_posts p
				left join wp_postmeta m_garo on m_garo.post_id = p.ID and m_garo.meta_key = 'global_average_rating_overall'
				left join wp_postmeta m_ero on m_ero.post_id = p.ID and m_ero.meta_key = 'editor_rating_overall'
				left join wp_postmeta m_lrb on m_lrb.post_id = p.ID and m_lrb.meta_key = 'last_rated_by'
				left join wp_postmeta m_aro on m_aro.post_id = p.ID and m_aro.meta_key = 'average_rating_overall'
				left join wp_comments c on c.comment_ID = m_lrb.meta_value
				left join wp_commentmeta cm on cm.comment_id = c.comment_ID and cm.meta_key = 'rating_overall'",

				3 => "
				join wp_term_relationships tr on p.ID = tr.object_id and
					tr.term_taxonomy_id in $terms_in_param",

			4 => "
			where " .
				(isset($extra_sql['! where']) ?
					$extra_sql['! where'] :
					("
					p.post_type = 'easyrp_review' and
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

	public function latest_reviews($count = 3, $term_taxonomy_id = null)
	{
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
			where post_type = 'easyrp_review' and post_status = 'publish'
			order by m_lrw.meta_key desc, post_modified_gmt desc
			limit %d;
			",
		);

		if ($term_taxonomy_id === null) {
			$sql = $sql[0] . $sql[2];

			return $wpdb->get_results($wpdb->prepare($sql, $count));
		} else {
			$sql = implode('', $sql);

			return $wpdb->get_results($wpdb->prepare($sql, $term_taxonomy_id, $count));
		}
	}



	// activate/deactivate

	public function activate()
	{
		require_once $this->path . '/activate.php';
	}

	public function deactivate()
	{
		require_once $this->path . '/deactivate.php';
	}



	public $comment_rating_cats;

	// register Review custom post type and related taxonomies

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

		register_taxonomy(
			self::prefix . 'rating_category',
			null,
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
				'rewrite'             => array('slug' => self::prefix . 'rating_category'),
			)
		);

		register_taxonomy_for_object_type(
			self::prefix . 'rating_category',
			self::prefix . 'review'
		);

		// register_taxonomy_for_object_type(
		// 	self::prefix . 'rating_category',
		// 	'post'
		// );
	}

	private function create_default_review_rating_categories()
	{
		$r = wp_insert_term(
			"General Ratings",
			self::prefix . 'rating_category',
			array(
				'slug' => 'general_ratings',
			)
		);

		wp_insert_term(
			"Overall",
			self::prefix . 'rating_category',
			array(
				'slug' => 'overall',
				'parent' => $r['term_id'],
			)
		);

		wp_insert_term(
			"Editors' Ratings",
			self::prefix . 'rating_category',
			array(
				'slug' => 'editors_ratings',
			)
		);

		wp_insert_term(
			"Users' Ratings",
			self::prefix . 'rating_category',
			array(
				'slug' => 'users_ratings',
			)
		);
	}

	private function get_rating_categories()
	{
		$cats = get_terms(self::prefix . 'rating_category', array(
			'hide_empty' => false,
			'orderby' => 'slug',
		));
		// d($cats);

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
		// d($rating_categories_groups_ids);

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

	private function comment_rating_widget($cat, $rating = null)
	{
		ob_start();
		require $this->frontend_template_path('comment_rating_ctrl.php');
		return ob_get_clean();
	}

	private function update_average_ratings($post_id)
	{
		$comments = get_comments(array(
			'post_id' => $post_id,
		));
		$authors = array();
		$ratings_to_average = array();

		foreach ($comments as $comment) {
			
			if (isset($authors[$comment->comment_author_email])) continue;
			$authors[$comment->comment_author_email] = true;

			foreach ($this->comment_rating_cats as $cat) {
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
}
