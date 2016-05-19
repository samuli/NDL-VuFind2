finna = $.extend(finna, {
    organisationMap: function() {
        var holder = null;
        var imgPath = null;
        var map = null;
        var mapMarkers = {};
        var selectedMarker = null;

        var draw = function(organisations) {
            var options = {
                zoom: 8,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };
            map = new google.maps.Map(holder, options);

            addMyLocationButton();

            var info = new google.maps.InfoWindow();
            var me = $(this);

            // Map points
            $.each(organisations, function(ind, obj) {
                if (obj.address != null && obj.address.coordinates != null) {
                    var markerIcon = obj['map']['icon'];
                    var infoWindowContent = obj['map']['info'];
                    
                    var marker = new google.maps.Marker({
                        index: obj.id, 
                        tooltipTitle: obj.name, 
                        content: infoWindowContent, 
                        icon: markerIcon, 
                        map: map,
                        position: new google.maps.LatLng(
                            obj.address.coordinates.lat, obj.address.coordinates.lon
                        ), 
                        clickable: true
                    });
                    marker.tooltipContent = marker.tooltipTitle;
                    google.maps.event.addListener(marker, 'click', function() {
                        me.trigger('marker-click', obj.id);
                        info.setContent(marker.content);
                        info.open(map, marker);
                        map.setZoom(13);
                        map.panTo(marker.position);
                    });
                    
                    google.maps.event.addListener(marker, 'mouseover', function() {
                        if (window.location.hash.substr(1) != marker.index) {
                            var point = fromLatLngToPoint(marker.getPosition(), map);
                            me.trigger(
                                'marker-mouseover', 
                                {id: obj.id, x: parseInt(point.x), y: parseInt(point.y)}
                            );

                        }
                    });
                    google.maps.event.addListener(marker, 'mouseout', function () {
                        me.trigger('marker-mouseout');
                    });
                    google.maps.event.addListener(marker, 'infoWindowClose', function () {
                        info.close(map, marker);
                        map.setZoom(11);
                    });

                    mapMarkers[obj.id] = marker;
                }
            });
        };

        var resize = function() {
            google.maps.event.trigger(map, "resize");
        };

        var selectMarker = function(id) {
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
            google.maps.event.trigger(selectedMarker, 'infoWindowClose');
        };

        var setLegend = function(legend) {
            map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(legend[0]);
        };

        var addMyLocationButton = function() {
            var marker = new google.maps.Marker({
                map: map,
                icon: imgPath + 'own-location.png',
            });

            var btn = $('.my-location-btn').removeClass('hide');;
            google.maps.event.addListener(map, 'dragend', function() {
                btn.find('.img').toggleClass("my-location", false);
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
                        var point = new google.maps.LatLng(crd.latitude, crd.longitude);
                        marker.setPosition(point);
                        map.setCenter(point);
                        map.setZoom(13);
                        btn.find('.img').toggleClass("my-location", true);
                    }, function() {}, options);
                } else {
                    btn.find('.img').toggleClass("my-location", false);
                }
            });
            
            btn[0].index = 1;
            map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(btn[0]);
        };

        var fromLatLngToPoint = function(latLng) {
            var topRight = map.getProjection().fromLatLngToPoint(map.getBounds().getNorthEast());
            var bottomLeft = map.getProjection().fromLatLngToPoint(map.getBounds().getSouthWest());
            var scale = Math.pow(2, map.getZoom());
            var worldPoint = map.getProjection().fromLatLngToPoint(latLng);
            return new google.maps.Point(
                (worldPoint.x - bottomLeft.x) * scale, 
                (worldPoint.y - topRight.y) * scale
            );
        };

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
