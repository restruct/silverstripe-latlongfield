(function($) {
	$(document).ready(function(){
		$.entwine('ss', function($) {

			$('input.latlong').entwine({
				onmatch: function () {
					let input = this;
					input.locationPicker($(this).data('locationpickeroptions'));

					input.parent().find('.btn-latlong-clear').click(function () {
						input.val(null);
					})
				}
			});

		});
	});
})(jQuery);