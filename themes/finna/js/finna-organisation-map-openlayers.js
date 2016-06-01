finna = $.extend(finna, {
    organisationMap: function() {
        var zoomLevel = {far: 10, close: 14};
        var holder = null;
        var imgPath = null;
        var map = null;
        var legend = null;
        var view = null;
        var mapMarkers = {};
        var selectedMarker = null;
        var infoInterval = null;
        var defaultId = null;
        var infoWindow = null;
        var organisations = null;

        var draw = function(organisationList) {
            var me = $(this);
            organisations = organisationList;

            var coordinates = organisations[defaultId].address.coordinates;
            view = new ol.View();
            reset();


            if (legend) {
                map.addControl(legend);
            }

            map = new ol.Map({
                target: $(holder).attr('id'),
                renderer: 'canvas',
                layers: [
                    new ol.layer.Tile({source: new ol.source.OSM()})
                ],
                view: view
            });

            infoWindow = new ol.Overlay.Popup();
            map.addOverlay(infoWindow);

            addMyLocationButton(map, $(this), holder);

            // Map points
            $.each(organisations, function(ind, obj) {
                var cnt = 0;
                if (obj.address != null && obj.address.coordinates != null) {
                    var markerIcon = obj['map']['icon'];
                    var infoWindowContent = obj['map']['info'];
                    var point = obj.address.coordinates;

                    var el = $('<img/>').attr('src', markerIcon).addClass('marker')
                        .data('id', obj.id).data('lat', point.lat).data('lon', point.lon);
                    
                    el.on("click", function() {
                        infoWindow.hide();

                        me.trigger('marker-click', obj.id);

                        var coord = latLonToCoord($(this).data('lat'), $(this).data('lon'));
                        /*
                        var point = map.getPixelFromCoordinate(coord);
                        

                        
                        var coord2 = map.getCoordinateFromPixel([point[0], point[1]-14.0]);

                        console.log("coord: %o", coord);
                        console.log("point: %o", point);
                        console.log("coord2: %o", coord2);
*/
                        view.setZoom(zoomLevel.close);
                        view.setCenter(coord);

                        clearInterval(infoInterval);
                        infoInterval = setTimeout(function() {
                            infoWindow.show(coord, infoWindowContent);
                        }, 100);
                    });

                    el.on('mouseover', function(ev) {
                        var coord = latLonToCoord($(this).data('lat'), $(this).data('lon'));
                        var point = map.getPixelFromCoordinate(coord);

                        me.trigger(
                            'marker-mouseover', 
                            {id: $(this).data('id'), x: parseInt(point[0]), y: parseInt(point[1])}
                        );
                    });

                    el.on('mouseout', function(ev) {
                        me.trigger('marker-mouseout');
                    });

                    map.addOverlay(new ol.Overlay({
                        position: latLonToCoord(point.lat, point.lon),
                        element: el
                    }));

                    mapMarkers[obj.id] = el;
                }
            });
        };
        
        var reset = function() {
            var coordinates = organisations[defaultId].address.coordinates;
            view.setCenter(latLonToCoord(coordinates.lat, coordinates.lon));
            view.setZoom(zoomLevel.far);
        };

        var resize = function() {
            map.updateSize();
        };

        var selectMarker = function(id) {
            var marker = null;
            if (id in mapMarkers) {
                marker = mapMarkers[id];
                if (!marker.data('lat')) {
                    marker = null;
                }
            }
            if (!marker && selectedMarker) {
                hideMarker();
                return;
            }
            marker.trigger('click');
            selectedMarker = marker;
        };

        var hideMarker = function() {
            infoWindow.hide();
        };

        var setLegend = function(legend) {
            var control = new ol.control.Control({
                element: legend[0]
            });
            legend = control;
        };

        var addMyLocationButton = function(map, me, mapHolder) {
            if (navigator.geolocation) {
                var view = map.getView();
                var btn = $(".my-location-btn").removeClass('hide');

                btn.on('click', function() {
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        var coord = latLonToCoord(pos.coords.latitude, pos.coords.longitude);
                        view.setZoom(zoomLevel.close);
                        view.setCenter(coord);
                        
                        $('.my-location-marker').not('.template').remove();
                        var el = $('.my-location-marker.template').clone().removeClass('hide template');
                        map.addOverlay(new ol.Overlay({
                            position: map.getView().getCenter(),
                            element: el
                        }));
                    });
                });
                
                var control = new ol.control.Control({
                    element: btn[0]
                });
                map.addControl(control);
            }
        };

        var latLonToCoord = function(lat, lon) {
            return ol.proj.transform([lon, lat], 'EPSG:4326', 'EPSG:3857');
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
            setLegend: setLegend,
            init: init,
            draw: draw
        };
        return my;
    }
});
