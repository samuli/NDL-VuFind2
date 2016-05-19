/*global VuFind*/
finna.organisationInfoPage = (function() {
    var updateURL = false;
    var parent = null;
    var holder = null;
    var service = null;
    var infoWidget = null;
    var organisationList = {};
    //var organisationListResponse = null;
    var map = null;
    var mapHolder = null;

    var loadOrganisationList = function(id) {
        parent = holder.data('parent');
        if (typeof parent == 'undefined') {
            return;
        }

        service.getOrganisations('page', parent, function(response) {
            if (response === false) {
                
            } else {
                var cnt = 0;
                $.each(response['list'], function(ind, obj) {
                    organisationList[obj.id] = obj;
                    cnt++;
                });

                infoWidget.organisationListLoaded(response);
                initMap();
                initSearch();
                $("#office-search").attr("placeholder", "Hae palvelupistettä (yhteensä: " + cnt + ")");
                updateSelectedOrganisation(id);
                updateURL = true;
            }        
        });
    };

    var initMap = function() {
        var imgPath = VuFind.path + '/themes/finna/images/';
        var openIcon = imgPath + 'map-marker-green.png';
        var closedIcon = imgPath + 'map-marker-red.png';
        var unknownIcon = imgPath + 'map-marker-black.png';

        $.each(organisationList, function(ind, obj) {
            // Map data (info bubble, icon)
            var bubble = $('.map-bubble-template').clone();
            bubble.find('.name').text(obj.name);
            var address = "";
            if ('street' in obj.address) {
                address += obj.address.street;
            }
            if ('zip' in obj.address) {
                address += obj.address.zip;
            }                
            bubble.find('.address').text(address);
            
            var openNow = null;
            if ('openNow' in obj) {
                openNow = obj.openNow;
            }
            
            if (openNow === null) {
                bubble.find('.schedule').hide();
            } else {
                bubble.find('.no-schedule').hide();
                // TODO
                bubble.find('.schedule .opens').text('1');
                bubble.find('.schedule .closes').text('2');
                //
                bubble.find('.schedule .open-closed').hide();
                bubble.find('.schedule .open-closed' + (obj.openNow ? '.open' : '.closed')).show();                    
            }
            
            var markerIcon = unknownIcon;
            if (openNow !== null) {
                markerIcon = openNow ? openIcon : closedIcon;
            }

            obj['map'] = {info: bubble.html(), icon: markerIcon};
        });

        map.draw(organisationList);

        // Expand map
        $('.expand-map').click (function() {
            mapHolder.toggleClass("expand", true);
            map.resize();
            $(this).hide();
            $('.contract-map').show();            
        });
        $('.contract-map').click (function() {
            mapHolder.toggleClass("expand", false);
            map.resize();
            $(this).hide();
            $('.expand-map').show();
        });

        // Legend
        var legend = $('#legend');
        map.setLegend(legend);
        legend.removeClass('hide');

        // hide spinner when markers are loaded and find information
        mapHolder.find('.fa-spinner').hide(); // TODO: tarvitaanko?
    };
    
    var initSearch = function() {
        $('#office-search').autocomplete({
            source: function (request, response) {
                service.search(request.term, parent, function(result) {
                    response(result);
                });
            },

            select: function(event, ui) {
                $('#office-search').val(ui.item.label);
                window.location.hash = ui.item.value;
                return false; // Prevent the widget from inserting the value.
            },
            focus: function (event, ui) {
                if ($(window).width() < 768) {
                    $('html, body').animate({
                        scrollTop: $('#office-search').offset().top - 5
                    }, 100);
                }
                return false; // Prevent the default focus behavior.
            },
            open: function(event, ui) {
                $('#office-search').off('menufocus hover mouseover mouseenter');
            },
            minLength: 0,
            delay: 100,
            appendTo: ".autocomplete-container",
            autoFocus: true,
        });

        // show list of
        $("#office-search").on('click', function() {
            $('#office-search').autocomplete("search", $(this).val());
        });
        $(".ui-autocomplete li").on('touchstart', function() {
            $('#office-search').autocomplete("search", $(this).val());
        });
        $('.btn-office-search').on('click', function() {
            $('#office-search').autocomplete("search", '');
            $('#office-search').focus();
            return false;
        });
    };

    var hideMapMarker = function() {
        $('#marker-tooltip').hide();
    };

    var updateSelectedOrganisation = function(id) {
        holder.find('.error, .info-element').hide();
        infoWidget.showDetails(id, '', true);

        if (id in organisationList) {
            var data = organisationList[id];
            if ('address' in data && 'coordinates' in data.address) {
                $('#office-search').val('');
                map.selectMarker(id);
                return;
            }
        }
    };

    var updateGeneralInfo = function(data) {
        holder.find('.office-quick-information .service-title').text(data.name);
        if ('address' in data) {
            holder.find('.office-links.address').html(data.address);
            var address = holder.find('.address-contact');
            address.show().find('> p').html(data.address);
        }
        if ('email' in data) {
            var email = data['email'];
            holder.find('.email').attr('href', 'mailto:' + email).show();
            holder.find('.email span.email').text(email.replace('@','(at)'));
            
            holder.find('.email-contact').show();
        }
        if ('homepage' in data) {
            holder.find('.office-website > a').attr('href', data['homepage']);
            holder.find('.office-website').show();
        }
        
        if ('routeUrl' in data) {
            holder.find('.office-links.route').attr('href', data['routeUrl']).show();
        }

        var desc = holder.find('.office-quick-information .office-description');
        if ('description' in data.details) {
            desc.html(data.details.description).show();
        }

        var longDesc = holder.find('.office-description.description-long');
        if ('description' in data.details) {
            longDesc.html(data.details.description).show();
        }
        
        if ('links' in data.details) {
            var links = data.details['links'];
            if (links.length) {
                var btn = holder.find('.social-button');
                btn.find('> a').attr('href', links[0]['url']);
                btn.show();
            }
        }

        if ('openToday' in data.details) {
            var times = data.details.openToday[0];
            if (times && 'opens' in times && 'closes' in times) {
                var timeOpen = holder.find('.time-open');
                timeOpen.find('.opens').text(times.opens);
                timeOpen.find('.closes').text(times.closes);
                timeOpen.show();
            }
        }
        if ('openNow' in data) {
            holder.find('.open-or-closed > span.library-is-' + (data.openNow ? 'open' : 'closed')).show();
        }

        var img = holder.find('.building-image');
        if ('pictures' in data.details) {
            var src = data.details.pictures[0].url;
            img.show();
            if (img.attr('src') != src) {
                img.attr('src', src);
                img.fadeTo(0, 0);
                img.on('load', function () {
                    $(this).stop(true, true).fadeTo(300, 1);
                });
            } else {
                img.fadeTo(300, 1);
            }
        } else {
            img.hide();
        }

        if ('buildingYear' in data.details) {
            var year = holder.find('.building-year');
            year.find('> span').text(data.details.buildingYear);
            year.show();
        }

        if ('phone' in data.details) {
            var phones = holder.find('.phone-numbers');
            phones.find('> p').html(data.details.phone);
            phones.show();
        }
    };

    var updateServices = function(data) {
        if ('allServices' in data.details) {
            var serviceHolder = holder.find('.service-list').empty();
            $(data.details.allServices).each(function(ind, obj) {
                var li = $('<li/>');
                li.append($('<strong/>').text(obj[0]));
                if (obj.length > 0) {
                    li.append($('<p/>').html(obj[1]));
                }
                li.appendTo(serviceHolder);
            });
        }
    };

    var updateRSSFeeds = function(data) {
        if ('rss' in data.details) {
            $(data.details.rss).each(function(ind, obj) {
                var url = obj['url'];
                var id = obj['id'];
                if (id != 'news' && id != 'events') {
                    return false;
                }
                holder.find('.feed-container.' + id + '-feed')
                    .empty().show()
                    .data('url', encodeURIComponent(obj['url']))
                    .data('feed', 'organisation-info-' + encodeURIComponent(id))
                    .closest('.rss-container').show();

                finna.feed.init(holder);
            });
        }
    };

    var getService = function() {
        return service;
    };

    var getOrganisationFromURL = function() {
        if (window.location.hash != "") {
            return parseInt(window.location.hash.replace('#',''));
        }
        return false;
    };

    var my = {
        init: function(defaultId) {
            var imgPath = VuFind.path + '/themes/finna/images/';
            mapHolder = $('.map-widget');
            map = finna.organisationMap();
            map.init(mapHolder[0], imgPath, defaultId);

            $(map).on('marker-click', function(ev, id) { 
                if (updateURL) {
                    window.location.hash = id;
                }
                hideMapMarker();
            });

            $(map).on('marker-mouseout', function(ev) { 
                hideMapMarker();
            });

            $(map).on('marker-mouseover', function(ev, data) { 
                var tooltip = $('#marker-tooltip');
                var name = organisationList[data.id].name;
                tooltip.html(name).css({
                    'left': data.x,
                    'top': data.y - 65
                });
                tooltip.css({'margin-left': -(tooltip.outerWidth())/2 + 10}).show();
            });

            $(map).on('my-location', function(ev, mode) { 
                $('.my-location-btn .img').toggleClass('my-location', mode);
            });

            service = finna.organisationInfo();
            holder = $('.organisation-info-page');

            infoWidget = finna.organisationInfoWidget();
            
            var widgetHolder = holder.find('.organisation-info');
            widgetHolder.on('detailsLoaded', function(ev, id) {
                var info = service.getDetails(id);
                updateGeneralInfo(info);
                updateServices(info);
                updateRSSFeeds(info);
            });

            infoWidget.init(widgetHolder, service);

            window.onhashchange = function() {
                if (id = getOrganisationFromURL()) {
                    updateSelectedOrganisation(id);
                }

                // Blur so that mobile keyboard is closed
                $('#office-search').blur();
            }

            var library = holder.data('library');
            if (hash = getOrganisationFromURL()) {
                library = hash;
            }
            loadOrganisationList(library);
        }
    };
    return my;

})(finna);
