function getQueryParam(param) {
    location.search.substr(1)
        .split("&")
        .some(function(item) { // returns first occurence and stops
            return item.split("=")[0] == param && (param = item.split("=")[1])
        })
    return param;
}

finna = $.extend(finna, {
    organisationMap: function() {
        var zoomLevel = {initial: 27, far: 5, close: 15};
        var holder = null;
        var mapTileUrl = null;
        var attribution = null;
        var imgPath = null;
        var map = null;
        var view = null;
        var mapMarkers = {};
        var markers = [];
        var selectedMarker = null;
        var infoInterval = null;
        var defaultId = null;
        var infoWindow = null;
        var organisations = null;
        var initialZoom = true;

        var draw = function(organisationList, id) {
            defaultId = id;
            var me = $(this);
            organisations = organisationList;

            map = L.map($(holder).attr('id'), {
                minZoom: zoomLevel.far,
                maxZoom: 18,
                zoomDelta: 0.1,
                zoomSnap: 0.1
            });

            L.tileLayer(mapTileUrl, {
                attribution: attribution,
                tileSize: 512,
                zoomOffset: -1
            }).addTo(map);
            
            // Center popup
            map.on('popupopen', function(e) {
                map.setZoom(zoomLevel.close, {animate: false});

                var px = map.project(e.popup._latlng); 
                px.y -= e.popup._container.clientHeight/2;
                map.panTo(map.unproject(px), {animate: false});
            });
            
            map.on('popupclose', function(e) {
                selectedMarker = null;
            });
           
            L.control.locate().addTo(map);

            var LeafIcon = L.Icon.extend({
                options: {
                    iconSize:     [21, 35],
                    iconAnchor:   [10, 35],
                    popupAnchor:  [0, -36],
                    labelAnchor: [-5, -86]
                }
            });

            // Map points
            $.each(organisations, function(ind, obj) {
                var cnt = 0;
                if (obj.address != null && obj.address.coordinates != null) {
                    var markerIcon = new LeafIcon({iconUrl: obj['map']['icon']});

                    var infoWindowContent = obj['map']['info'];
                    var point = obj.address.coordinates;

                    var marker = L.marker(
                        [point.lat, point.lon], 
                        {icon: markerIcon}
                    ).addTo(map);
                    marker.on('mouseover', function(ev) {
                        if (marker == selectedMarker) {
                            return;
                        }
                        var holderOffset = $(holder).offset();
                        var offset = $(ev.originalEvent.target).offset();
                        var x = offset.left - holderOffset.left;
                        var y = offset.top - holderOffset.top;
                        
                        me.trigger(
                            'marker-mouseover', {id: obj.id, x: x, y: y}
                        );
                    });

                    marker.on('mouseout', function(ev) {
                        me.trigger('marker-mouseout');
                    });

                    marker.on('click', function(ev) {
                        me.trigger('marker-click', obj.id);
                    });

                    marker
                        .bindPopup(infoWindowContent, {zoomAnimation: false, autoPan: false})
                        .addTo(map);

                    mapMarkers[obj.id] = marker;
                    markers.push(marker);
                }
            });

            reset();
        };
        
        var reset = function() {
            group = new L.featureGroup(markers);
            var bounds = group.getBounds();
            // Fit markers to screen
            map.fitBounds(bounds, {zoom: {animate: true}});

            selectedMarker = null;
        };

        var resize = function() {
            map.invalidateSize(true);
        };

        var selectMarker = function(id) {
            var marker = null;
            if (id in mapMarkers) {
                marker = mapMarkers[id];
            }
            if (selectedMarker) {
                if (selectedMarker == marker) {
                    return;
                } else if (!marker) {
                    hideMarker();
                    return;
                }
            }

            marker.openPopup();
            selectedMarker = marker;
        };

        var hideMarker = function() {
            if (selectedMarker) {
                selectedMarker.closePopup();
            }
        };

        var init = function(_holder, _imgPath, _mapTileUrl, _attribution) {
            holder = _holder;
            imgPath = _imgPath;
            mapTileUrl = _mapTileUrl;
            attribution = _attribution;
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
