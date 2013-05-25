jQuery(document).ready(function ($) {

	var stars_dir_path = '<?php echo $_GET['stars_dir_path'] ?>';

	$('.rating-ctrl').each(function (index) {

		var $this = $(this);
		var path = $this.attr('data-stars-path') !== undefined ?
					$this.attr('data-stars-path') :
					stars_dir_path;
		var target = $this.attr('data-target');
		var $target = $(target);
		
		if ($target) $target.hide();

		$this.raty({
			path: path,
			hints: ['1', '2', '3', '4', '5'],
			space: false,
			target: target,
			targetKeep: true,
			scoreName: ($this.attr('data-score-name') !== undefined ?
							$this.attr('data-score-name') : 'score'),
			half: $this.attr('data-halves') !== undefined,
			cancel: $this.attr('data-cancel') !== undefined,
			readOnly: $this.attr('data-read-only') !== undefined,
			size: ($this.attr('data-star-size') !== undefined ?
					+ $this.attr('data-star-size') : 16),
			score: function () {
				return $(this).attr('data-rating');
			}
		});
	});
 	
});