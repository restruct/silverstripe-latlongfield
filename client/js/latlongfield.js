(function($){
	jQuery.entwine('ss', function($){
		$('input.latlong').entwine({
			onadd: function() {
				$(this).locationPicker($(this).data('locationpickeroptions'));
//				$(this).locationPicker({
//					css_width: "500px",
//					css_height: "300px",
//                    css_display: 'block',
//					defaultLat: 51.92556,
//					defaultLng: 4.47646,
//					maptype: "ROADMAP",
//					defaultZoom: 15
//				});
			}
		});
	});
})(jQuery);