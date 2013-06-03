<?php require dirname(__FILE__) . '/../../config.php' ?>
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
			hints: ['<?php echo implode("', '", $easyrp_config->stars_hints) ?>'],
			number: <?php echo $easyrp_config->stars_no ?>,
			numberMax: <?php echo $easyrp_config->stars_no ?>,
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