<p class="comment-form-rating">
	<label>
		<?php _e($cat->name, self::text_domain) ?>:
	</label>
	<?php
	echo $this->rating_ctrl(array(
		'score_name' => "rating_{$cat->slug}",
		'halves' => true,
		'cancel' => true,
		'star_size' => 17,
	));
	?>
</p>