jQuery(document).ready(function() {
	if (!jQuery('#crawler-progress').length) return;

	var interval = setInterval(checkProgress, 3000);

	function checkProgress() {
		jQuery.get(location.href + 'status/', function(response) {
			if (jQuery('#complete', response).length) return;

			clearInterval(interval);
			location.reload(true);
		});
	}
});