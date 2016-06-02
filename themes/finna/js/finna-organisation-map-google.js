finna = $.extend(finna, {
    organisationMap: function() {
        var zoomLevel = {far: 11, close: 13};
        var holder = null;
        var imgPath = null;
        var defaultId = null;
        var map = null;
        var mapMarkers = {};
        var selectedMarker = null;
        var infoWindow = null;
        var infoInterval = null;
        var centerMap = false;
        var mapInited = false;

        var draw = function(organisations) {
            var options = {
                zoom: zoomLevel.far,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };
            map = new google.maps.Map(holder, options);


            infoWindow = new google.maps.InfoWindow();
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
                        
                        infoWindow.setContent(marker.content);

                        if (!mapInited) {
                            setTimeout(function() {
                                infoWindow.open(map, marker);
                            }, 1000);
                        } else {
                            infoWindow.open(map, marker);
                        }
                        
                        if ((!mapInited || centerMap) || map.getZoom() != zoomLevel.close || centerMap) {
                            map.setZoom(zoomLevel.close);
                            map.panTo(marker.position);
                        }
                        mapInited = true;
                        centerMap = false;
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
                        infoWindow.close(map, marker);
                        map.setZoom(zoomLevel.far);
                    });

                    mapMarkers[obj.id] = marker;
                }
            });
            addMyLocationButton();
        };

        var reset = function() {
            var marker = mapMarkers[defaultId];
            map.setZoom(zoomLevel.far);
            map.panTo(marker.position);
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
            
            if (!marker) {
                if (selectedMarker) {
                    hideMarker();
                }
                return;
            }
            centerMap = true;
            google.maps.event.trigger(marker, 'click');
            selectedMarker = marker;
        };

        var hideMarker = function() {
            if (!selectedMarker) {
                return;
            }
            google.maps.event.trigger(selectedMarker, 'infoWindowClose');
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
                        map.setZoom(zoomLevel.close);
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

        var init = function(mapHolder, path, id) {
            holder = mapHolder;
            imgPath = path;
            defaultId = id;
        };
        
        var my = {
            hideMarker: hideMarker,
            reset: reset,
            resize: resize,
            selectMarker: selectMarker,
            init: init,
            draw: draw
        };
        return my;
    }
});
