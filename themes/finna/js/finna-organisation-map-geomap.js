finna = $.extend(finna, {
    organisationMap: function() {
        var holder = null;
        var imgPath = null;
        var map = null;
        var mapMarkers = {};
        var selectedMarker = null;


        var draw = function(organisations) {
            console.log("holder: %o", holder);


            var map = $(holder).geomap({
                center: [27, 66],
                scroll: 'off',
                zoom: 7,
                zoomMin: 4,
                zoomMax: 17,
                bboxchange: function() {
                    //$('.zoom-path').slider('setValue', $(this).geomap('option', 'zoom'));
                }
            });

            var me = $(this);


            // Map points
            $.each(organisations, function(ind, obj) {
                var cnt = 0;
                if (obj.address != null && obj.address.coordinates != null) {
                    var markerIcon = obj['map']['icon'];
                    var infoWindowContent = obj['map']['info'];
                    var point = obj.address.coordinates;

                    var html = '<img src="' + markerIcon + '"/>';
                    map.geomap('append', { type: 'Point', coordinates: [point.lon, point.lat] }, html);
                    
                    if (cnt == 0) {
                        map.geomap('option', 'center', [point.lon, point.lat]);
                    }
                }
            });

        };

        var resize = function() {
            //google.maps.event.trigger(map, "resize");
        };

        var selectMarker = function(id) {
            console.log("sel: " + id);
            return;

            var marker = null;
            if (id in mapMarkers) {
                marker = mapMarkers[id];
                if (!marker.position) {
                    marker = null;
                }
            }
            if (!marker && selectedMarker) {
                hideMarker();
                return;
            }
            google.maps.event.trigger(marker, 'click');
            selectedMarker = marker;
        };

        var hideMarker = function() {
            if (!selectedMarker) {
                return;
            }
            // google.maps.event.trigger(selectedMarker, 'infoWindowClose');
        };

        var setLegend = function(legend) {
            //map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(legend);
        };

        var addMyLocationButton = function() {
            return;

            var marker = new google.maps.Marker({
                map: map,
                icon: imgPath + 'own-location.png',
            });

            var controlDiv = document.createElement('div');
            $(controlDiv).addClass('my-location-btn');

            var btn = $('<button/>');
            btn.attr('title', 'Your Location'); // TODO: translate
            $(controlDiv).append(btn);

            var img = $('<div/>').addClass('img');
            btn.append(img);

            google.maps.event.addListener(map, 'dragend', function() {
                img.css('background-position', '0px 0px');
            });

            btn.on('click', function() {
                // TODO: animationInterval?
                if (navigator.geolocation) {
                    var options = {
                        enableHighAccuracy: true,
                        timeout: 5000,
                        maximumAge: 0
                    };
                    
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        var crd = pos.coords;
                        var latlng = new google.maps.LatLng(crd.latitude, crd.longitude);
                        marker.setPosition(latlng);
                        map.setCenter(latlng);
                        map.setZoom(13);
                        img.css('background-position', '-144px 0px');
                    }, function() {}, options);
                } else {
                    img.css('background-position', '0px 0px');
                }
            });
            
            controlDiv.index = 1;
            map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(controlDiv);
        };
        /*
        var fromLatLngToPoint = function(latLng) {
            var topRight = map.getProjection().fromLatLngToPoint(map.getBounds().getNorthEast());
            var bottomLeft = map.getProjection().fromLatLngToPoint(map.getBounds().getSouthWest());
            var scale = Math.pow(2, map.getZoom());
            var worldPoint = map.getProjection().fromLatLngToPoint(latLng);
            return new google.maps.Point(
                (worldPoint.x - bottomLeft.x) * scale, 
                (worldPoint.y - topRight.y) * scale
            );
        };*/

        var init = function(mapHolder, path) {
            holder = mapHolder;
            imgPath = path;
        };
        
        var my = {
            hideMarker: hideMarker,
            resize: resize,
            selectMarker: selectMarker,
            setLegend: setLegend,
            init: init,
            draw: draw
        };
        return my;
    }
});
