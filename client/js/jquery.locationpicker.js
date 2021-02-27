/*
 * http://webcoda.co.uk/2010/10/the-jquery-location-picker/
 */

(function ($) {

  //Attach this new method to jQuery
  $.fn.extend({

    //This is where you write your plugin's name
    locationPicker: function (options) {

      // EDIT: make options adjustable on instantiation
      var defaults = {
        css_width: "486px",
        css_height: "300px",
        css_backgroundColor: '#fff',
        css_border: '1px solid #ccc',
        css_borderRadius: 10,
        css_padding: 10,
        css_position: 'absolute',
        css_marginTop: '36px',
        css_display: 'none',
        defaultLat: 51.92556,
        defaultLng: 4.47646,
        maptype: "ROADMAP",
        defaultZoom: 15
      };
      var options = $.extend(defaults, options);

      function RoundDecimal(num, decimals) {
        var mag = Math.pow(10, decimals);
        return Math.round(num * mag) / mag;
      };

      var geocoder = new google.maps.Geocoder();

      //var mapsScript = document.createElement( 'script' );
      //mapsScript.type = 'text/javascript';
      //mapsScript.src = "http://maps.google.com/maps/api/js?sensor=false&callback=lptoremove";
      //$(this).before( mapsScript );

      //Iterate over the current set of matched elements
      return this.each(function () {

        var that = this;

        var setPosition = function (latLng, viewport) {
          var lat = RoundDecimal(latLng.lat(), 6);
          var lng = RoundDecimal(latLng.lng(), 6);
          marker.setPosition(latLng);
          if (viewport) {
            map.fitBounds(viewport);
            map.setZoom(map.getZoom() + 2);
          } else {
            map.panTo(latLng);
          }
          $(that).val(lat + "," + lng);
        }


        var id = $(this).attr('id');
        // var searchButton = $("<input class='picker-search-button btn btn-outline-dark' type='button' value='Search'/>");
        // $(this).after(searchButton);
        var searchButton = $(this).parent().find('.btn-latlong-search');

        var picker = $("<div id='" + id + "-picker' class='pickermap'></div>").css({
          width: options.css_width,
          backgroundColor: options.css_backgroundColor,
          border: options.css_border,
          padding: options.css_padding,
          borderRadius: options.css_borderRadius,
          position: options.css_position,
          marginTop: options.css_marginTop,
          display: options.css_display,
          zIndex: 99999
        });
        $(searchButton).after(picker);
        var mapDiv = $("<div class='picker-map'>Loading</div>").css({
          height: options.css_height
        });
        picker.append(mapDiv);

        // load correct pin location or default location on init
        var curPos = splitLatLng($(this).val()); // returns fals if invalid
        if (curPos) {
          var myLatlng = new google.maps.LatLng(curPos[0], curPos[1]);
        } else {
          // start out with default
          var myLatlng = new google.maps.LatLng(options.defaultLat, options.defaultLng);
        }

        var myOptions = {
          zoom: options.defaultZoom,
          center: myLatlng,
          mapTypeId: google.maps.MapTypeId[options.maptype],
          mapTypeControl: false,
          disableDoubleClickZoom: true,
          streetViewControl: false
        }
        var map = new google.maps.Map(mapDiv.get(0), myOptions);

        var marker = new google.maps.Marker({
          position: myLatlng,
          map: map,
          title: "Drag Me",
          draggable: true
        });

        google.maps.event.addListener(map, 'dblclick', function (event) {
          setPosition(event.latLng);
        });

        google.maps.event.addListener(marker, 'dragend', function (event) {
          setPosition(marker.position);
        });

        function getCurrentPosition() {
          var posStr = $(that).val();
          if (posStr != "") {
            var posArr = posStr.split(",");
            if (posArr.length == 2) {
              var lat = $.trim(posArr[0]);
              var lng = $.trim(posArr[1]);
              var latlng = new google.maps.LatLng(lat, lng);
              setPosition(latlng);
              return;
            }
            $(that).val("Invalid Position");
          }

        }

        function showPicker() {
          picker.fadeIn('fast');
          google.maps.event.trigger(map, 'resize');
          getCurrentPosition();
          map.setCenter(marker.position);
        }

        $(this).focus(function () {
          var address = $(that).val();
          if (isLatLng(address)) {
            showPicker();
          }
        });

        $(":input").focus(function () {
          if ($(this).attr('id') != $(that).attr('id')) {
            if ($(picker).children(this).length == 0) {
//                            if(!options.css_display=='block') picker.fadeOut('fast');
              picker.fadeOut('fast');
            }
          }
        });

        function isLatLng(val) {
          var LatLngArr = val.split(",");
          if (LatLngArr.length == 2) {
            if (isNaN(LatLngArr[0]) || isNaN(LatLngArr[1])) {
              return false;
            } else {
              return true;
            }
          }
          return false;
        }

        function splitLatLng(val) {
          var LatLngArr = val.split(",");
          if (LatLngArr.length == 2) {
            if (isNaN(LatLngArr[0]) || isNaN(LatLngArr[1])) {
              return false;
            } else {
              return LatLngArr;
            }
          }
          return false;
        }

        function findAddress() {
          // allow setting custom fields to take the data from
          var addressfields = $(that).data('addressfields');
          if (addressfields) {
            var addresses = []; //eval($(that).data('selector'));
            $.each(addressfields, function (index, name) {
              addresses.push($('input[name="' + name + '"]').val());
            });
            var address = addresses.join(', ');
          } else {
            var address = $(that).val();
          }
          if (address == "") {
            alert("Please enter an address or Lat/Lng position.");
          } else {
            if (isLatLng(address)) {
              showPicker();
            } else {
              geocoder.geocode({'address': address/*, 'region': 'uk'*/}, function (results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                  setPosition(
                    results[0].geometry.location,
                    results[0].geometry.viewport
                  );
                  showPicker();
                } else {
                  alert("Geocode was not successful for the following reason: " + status);
                }
              });
            }
            $(that).focus();
          }
        }


        $(searchButton).click(function (event) {
          findAddress();
          event.stopPropagation();
        });

        $(that).keydown(function (event) {
          if (event.keyCode == '13') { // enter
            findAddress();
          }
        });

        $('html').click(function () {
//                    if(!options.css_display=='block') picker.fadeOut('fast');
          picker.fadeOut('fast');
        });

        $(picker).click(function (event) {
          event.stopPropagation();
          //$(that).focus();
        });

        $(this).click(function (event) {
          event.stopPropagation();
        });

      });


    }

  });

})(jQuery);