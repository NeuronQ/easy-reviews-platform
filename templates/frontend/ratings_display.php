<div class='ratings'>

	<?php if ($this->editor_rating_cats): ?>

		<h4>Editor Ratings</h4>
		<ul class="editor-ratings">
			<?php
			foreach ($this->editor_rating_cats as $cat) {
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

	<?php if ($this->comment_rating_cats): ?>

		<h4>User Ratings</h4>
		<ul class="user-ratings">
			<?php
			foreach ($this->comment_rating_cats as $cat) {
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