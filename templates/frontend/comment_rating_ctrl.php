<p class="comment-form-rating">
	<label>
		<?php _e($cat->name, self::text_domain) ?>:
	</label>
	<?php
	$rating_ctrl_args = array(
		'score_name' => "rating_{$cat->slug}",
		'halves' => true,
		'cancel' => true,
		'star_size' => 17,
	);
	if ($rating) $rating_ctrl_args['rating'] = $rating;
	echo $this->rating_ctrl($rating_ctrl_args);
	?>
</p>