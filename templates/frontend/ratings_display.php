<?php
d($post);
?>
<div class='ratings'>

	<?php
	$editor_rating_cats = $this->config->rated_post_types[$post->post_type]->editor_rating_cats;
	$comment_rating_cats = $this->config->rated_post_types[$post->post_type]->comment_rating_cats;
	if ($editor_rating_cats): ?>

		<h4>Editor Ratings</h4>
		<ul class="editor-ratings">
			<?php
			foreach ($editor_rating_cats as $cat) {
				$meta_key = 'editor_rating_' . $cat->slug;
				if ($rating = get_post_meta($post->ID, $meta_key, true)) {
					printf("<li><strong>{$cat->name}:</strong> %.2f / 5 ", (float) $rating);
					echo $this->rating_ctrl(array(
						'rating' => $rating,
						'read_only' => true,
					));
					echo "</li>";
				}
			}
			?>
		</ul>

	<?php endif ?>

	<?php if ($comment_rating_cats): ?>

		<h4>User Ratings</h4>
		<ul class="user-ratings">
			<?php
			foreach ($comment_rating_cats as $cat) {
				$meta_key = 'average_rating_' . $cat->slug;
				$rating = get_post_meta($post->ID, $meta_key, true);
				if ($rating) {
					printf("<li><strong>{$cat->name}:</strong> %.2f / 5 ", (float) $rating);			
					echo $this->rating_ctrl(array(
						'rating' => $rating,
						'read_only' => true,
					));
					echo "</li>";
				}
			}
			?>
		</ul>

	<?php endif ?>

</div>