(function($) {
	$(function () {
		$('select[name="wp_avatar_user"]').change(function() {
			$(this).closest("form").submit();
		});
		$(document).on('change', 'input[name=wp_avatar]', function () {
			$(this).closest("form").submit();
		});
	});
})(jQuery);