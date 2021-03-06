<ul class='ratings'>

	<?php
	$comment_rating_cats = $this->config
		->rated_post_types[get_post_type($comment->comment_post_ID)]
		->comment_rating_cats;
	foreach ($comment_rating_cats as $cat) {
		$meta_key = 'rating_' . $cat->slug;
		
		if ($rating = get_comment_meta($comment_id, $meta_key, true)) {
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