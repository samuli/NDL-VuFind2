finna = $.extend(finna, {
    organisationMap: function() {
        var holder = null;
        var imgPath = null;
        var map = null;
        var mapMarkers = {};
        var selectedMarker = null;
        var infoInterval = null;
        var defaultId = null;

        var draw = function(organisations) {
            var me = $(this);

            var coordinates = organisations[defaultId].address.coordinates;
            var view = new ol.View({
                zoom: 10,
                center: latLonToCoord(coordinates.lat, coordinates.lon)
            });

            map = new ol.Map({
                target: $(holder).attr('id'),
                renderer: 'canvas',
                layers: [
                    new ol.layer.Tile({source: new ol.source.OSM()})
                ],
                view: view
            });

            var info = new ol.Overlay.Popup();
            map.addOverlay(info);

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
                        info.hide();

                        me.trigger('marker-click', obj.id);
                        var coord = latLonToCoord($(this).data('lat'), $(this).data('lon'));
                        view.setZoom(13);
                        view.setCenter(coord);

                        clearInterval(infoInterval);
                        infoInterval = setTimeout(function() {
                            info.show(coord, infoWindowContent);
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
            if (!selectedMarker) {
                return;
            }
        };

        var setLegend = function(legend) {
            var control = new ol.control.Control({
                element: legend[0]
            });
            map.addControl(control);
        };

        var addMyLocationButton = function(map, me, mapHolder) {
            var view = map.getView();
            var btn = $(".my-location-btn").removeClass('hide');

            var geolocation = new ol.Geolocation({
                projection: view.getProjection()
            });
            
            geolocation.on('change', function(ev) {
                var pos = geolocation.getPosition();
                view.setCenter(pos);
                view.setZoom(14);
                
                $('.my-location-marker').not('.template').remove();
                var el = $('.my-location-marker.template').clone().removeClass('hide template');
                map.addOverlay(new ol.Overlay({
                    position: map.getView().getCenter(),
                    element: el
                }));
                geolocation.setTracking(false);
            });

            geolocation.on('error', function(evt) {
            });

            btn.on('click', function() {
                geolocation.setTracking(true);
            });

            var control = new ol.control.Control({
                element: btn[0]
            });
            map.addControl(control);
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
            resize: resize,
            selectMarker: selectMarker,
            setLegend: setLegend,
            init: init,
            draw: draw
        };
        return my;
    }
});
