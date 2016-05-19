/*global VuFind*/
finna = $.extend(finna, {
    organisationInfoWidget: function() {
        var holder = null;
        var service = null;
        var currentScheduleInfo = null;
        var schedulesLoading = false;

        var loadOrganisationList = function() {
            holder.find('.week-navi.prev-week').fadeTo(0,0);

            var parent = holder.data('parent');
            if (typeof parent == 'undefined') {
                return;
            }

            toggleSpinner(true);
            holder.find('.error,.info-element').hide();
            service.getOrganisations(holder.data('target'), parent, function(response) {
                if (response === false) {
                    holder.html('<!-- Organisation info could not be loaded');            
                } else {
                    organisationListLoaded(response);
                }        
            });
        };

        var organisationListLoaded = function(data) {
            console.log("loaded: %o", data);

            var organisationList = data['list'];
            var id = data['id'];

            var found = false;
            var menu = holder.find('.organisation');
            $.each(organisationList, function(ind, obj) {
                if (id == obj['id']) {
                    found = true;
                }
                $('<option/>', {value: obj['id'], text: obj['name']}).appendTo(menu);
            });

            if (!found) {
                id = menu.find('option').eq(0).val();
            }
            menu.val(id);
            menu.on('change', function() {
                showDetails($(this).val(), $(this).find('option:selected').text(), false);
            });

            var organisation = holder.find('.organisation option:selected');
            showDetails(organisation.val(), organisation.text(), false);

            toggleSpinner(false);
            holder.find('.content').removeClass('hide');

            attachWeekNaviListener();
        };
        
        var attachWeekNaviListener = function() {
            console.log("attach");
            holder.find('.week-navi').unbind('click').click(function() {
                console.log("click");

                if (schedulesLoading) {
                    return;
                }
                schedulesLoading = true;

                var parent = holder.data('parent');
                var id = holder.data('id');
                var dir = parseInt($(this).data('dir'));
                
                holder.find('.week-text .num').text(holder.data('week-num') + dir);
                $(this).attr('data-classes', $(this).attr('class'));
                $(this).removeClass('fa-arrow-right fa-arrow-left');
                $(this).addClass('fa-spinner fa-spin');
                
                service.getSchedules(holder.data('target'), parent, id, holder.data('period-start'), dir, false, false, function(response) {
                    schedulesLoaded(id, response);
                });
            });
        };

        var showDetails = function(id, name, allServices) {
            holder.find('.error,.info-element').hide();
            holder.find('.is-open').hide();

            var parent = holder.data('parent');
            var data = service.getDetails(id);
            if (!data) {
                return;
            }

            holder.data('id', id);

            holder.find('.is-open').hide();
            if ('openNow' in data) {
                holder.find('.is-open' + (data['openNow'] ? '.open' : '.closed')).show();
            }

            if ('email' in data) {
                holder.find('.email').attr('href', 'mailto:' + data['email']).show();
            }

            if ('homepage' in data) {
                $('a.details').attr('href', data['homepage']);
                $('.details-link').show();
            }

            if ('routeUrl' in data) {
                holder.find('.route').attr('href', data['routeUrl']).show();
            }

            if ('mapUrl' in data && 'address' in data) {
                var map = holder.find('.map');
                map.find('> a').attr('href', data['mapUrl']);
                map.find('.map-address').text(data['address']);
                map.show();
            }

            service.getSchedules(
                holder.data('target'), parent, id, 
                holder.data('period-start'), null, true, allServices, 
                function(response) {
                    detailsLoaded(id, response);
                    schedulesLoaded(id, response);
                    holder.trigger('detailsLoaded', id);
                }
            );
        };

        var schedulesLoaded = function(id, response) {
            schedulesLoading = false;

            holder.find('.week-navi-holder .week-navi').each(function() {
                if (classes = $(this).data('classes')) {
                    $(this).attr('class', classes);
                }
            });

            if ('periodStart' in response) {
                holder.data('period-start', response['periodStart']);
            }

            if ('weekNum' in response) {
                var week = parseInt(response['weekNum']);
                holder.data('week-num', week);
                holder.find('.week-navi-holder .week-text .num').text(week);
            }

            updatePrevBtn(response);

            var schedulesHolder = holder.find('.schedules');
            var hasSchedules = 'html' in response;
            if (hasSchedules) {
                schedulesHolder.find('.content').html(response['html']);
            } else {
                holder.find('.no-schedules').show();
            }

            holder.find('.week-navi-holder').toggle(hasSchedules);
            schedulesHolder.stop(true, false).fadeTo(200, 1);
        };

        var detailsLoaded = function(id, response) {
            toggleSpinner(false);

            if ('description' in response) {
                var info = response['description'];
                if (!currentScheduleInfo || info != currentScheduleInfo) {
                    currentScheduleInfo = info;
                    holder.find('.schedules-info .truncate-field, .more-link, .less-link').remove();
                    var infoHolder = holder.find('.schedules-info');
                    var truncateField 
                        = $('<div/>').addClass("truncate-field").attr('data-rows', 5).attr('data-row-height', 20);
                    $('<div/>').html(info).appendTo(truncateField);
                    infoHolder.show().append(truncateField);
                }
            } else {
                currentScheduleInfo = null;
            }

            if ('periodStart' in response) {
                holder.data('period-start', response['periodStart']);
            }

            updatePrevBtn(response);

            if ('phone' in response) {
                holder.find('.phone').attr('data-original-title', response['phone']).show();
            }

            if ('links' in response) {
                var links = response['links'];
                if (links.length) {
                    holder.find('.facebook').attr('href', links[0]['url']).show();
                }
            }

            var img = holder.find('.facility-image');
            if ('pictures' in response) {
                var src = response['pictures'][0].url;
                img.show();
                if (img.attr('src') != src) {
                    img.fadeTo(0, 0);
                    img.on('load', function () {
                        $(this).stop(true, true).fadeTo(300, 1);
                    });
                    img.attr('src', src).attr('alt', name);
                    img.closest('.info-element').show();
                } else {
                    img.fadeTo(300, 1);
                }
            } else {
                img.hide();
            }

            if ('services' in response) {
                $.each(response['services'], function(ind, obj) {
                    holder.find('.services .service-' + obj).show();
                });
            }

            finna.layout.initTruncate(holder);
        };

        var updatePrevBtn = function(response) {
            var prevBtn = holder.find('.week-navi.prev-week');
            if ('currentWeek' in response) {
                prevBtn.unbind('click').fadeTo(200, 0);
            } else {
                prevBtn.fadeTo(200, 1);
                attachWeekNaviListener();
            }
        };

        var toggleSpinner = function(mode) {
            var spinner = holder.find('.loader');
            if (mode) {
                spinner.fadeIn();
            } else {
                spinner.hide();
            }
        };

        var my = {
            loadOrganisationList: loadOrganisationList,
            organisationListLoaded: organisationListLoaded,
            showDetails: showDetails,
            init: function(_holder, _service) {
                holder = _holder;
                service = _service;
            }
        };
        return my;
    }
});
