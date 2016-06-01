/*global VuFind*/
finna = $.extend(finna, {
    organisationInfoWidget: function() {
        var holder = null;
        var service = null;
        var currentScheduleInfo = null;
        var schedulesLoading = false;
        var organisationList = {};
        
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
            var list = data['list'];
            var id = data['id'];

            var found = false;
            var menu = holder.find('.organisation');
            $.each(list, function(ind, obj) {
                if (id == obj['id']) {
                    found = true;
                }
                $('<option/>', {value: obj['id'], text: obj['name']}).appendTo(menu);
                organisationList[obj['id']] = obj;
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

            var week = parseInt(data['weekNum']);
            updateWeekNum(week);

            attachWeekNaviListener();
        };
        
        var attachWeekNaviListener = function() {
            holder.find('.week-navi').unbind('click').click(function() {
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
                
                service.getSchedules(
                    holder.data('target'), parent, id, holder.data('period-start'), dir, false, false, 
                    function(response) {
                        schedulesLoaded(id, response);
                    }
                );
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

            if ('openTimes' in data && 'openNow' in data.openTimes 
                && 'schedules' in data.openTimes && data.openTimes.schedules.length
            ) {
                holder.find('.is-open' + (data.openTimes.openNow ? '.open' : '.closed')).show();
            }

            if ('email' in data) {
                holder.find('.email').attr('href', 'mailto:' + data['email']).show();
            }

            if ('homepage' in data) {
                holder.find('a.details').attr('href', data['homepage']);
                holder.find('.details-link').show();
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
                    schedulesLoaded(id, data);
                    holder.trigger('detailsLoaded', id);
                }
            );
        };

        var getScheduleTableRow = function(today, date, day, info) {
            var schedulesHolder = holder.find('.schedules');
            var scheduleTable = schedulesHolder.find('table');
            var tr = scheduleTable.find('tr:first-child').clone();
            tr.removeClass('template hide');
            if (today) {
                tr.addClass('today');
            }
            tr.find('.date').text(date);
            tr.find('.day').not('.staff').find('.name').text(day);

            if (info !== null) {
                tr.find('.info').removeClass('hide').text(' (' + info + ')');
            }

            return tr;
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
                updateWeekNum(week);
            }
            updatePrevBtn(response);
            
            var schedulesHolder = holder.find('.schedules');
            var scheduleTable = schedulesHolder.find('table');
            scheduleTable.hide();

            var hasSchedules 
                = 'openTimes' in response && 'schedules' in response.openTimes 
                && response.openTimes.schedules.length > 0;

            if (hasSchedules) {
                var schedules = response.openTimes.schedules;
                scheduleTable.show();

                scheduleTable.find('tr').not('.template').remove();
                $.each(schedules, function(ind, obj) {
                    var today = 'today' in obj;
                    if (!('closed' in obj)) {
                        var cnt = 0;
                        $.each(obj['times'], function(ind, time) {
                            var date = cnt == 0 ? obj['date'] : '';
                            var day = cnt == 0 ? obj['day'] : 'staff';
                            var info = 'info' in time ? time.info : null;
                            var tr = getScheduleTableRow(today, date, obj['day'], info);
                            tr.find('.opens').text(time['opens']);
                            tr.find('.closes').text(time['closes']);                        

                            if (cnt > 0 && !time['selfservice']) {
                                tr.find('.day').hide();
                                tr.find('.day.staff').show().removeClass('hide');
                            }

                            scheduleTable.find('tbody').append(tr);
                            cnt++;
                        });
                    } else {
                        var info = 'info' in obj ? obj.info : null;
                        var tr = getScheduleTableRow(today, obj['date'], obj['day'], info);
                        scheduleTable.find('tbody').append(tr);
                        tr.find('.time').hide();
                        tr.find('.time.closed-today').show().removeClass('hide');
                    }
                });
            } else {
                var links = null;
                var data = organisationList[id];
                var linkHolder = holder.find('.mobile-schedules');
                linkHolder.empty();

                if (data.mobile) {
                    linkHolder.show();
                    if ('links' in data.details) {
                        $.each(data.details.links, function(ind, obj) {                            
                            var link = holder.find('.mobile-schedule-link-template').eq(0).clone();
                            link.removeClass('hide mobile-schedule-link-template');
                            link.find('a').attr('href', obj.url).text(obj.name);
                            link.appendTo(linkHolder);
                        });
                        links = true;
                    }
                }
                if (!links) {
                    holder.find('.no-schedules').show();
                }
            }

            holder.find('.week-navi-holder').toggle(hasSchedules);
            schedulesHolder.stop(true, false).fadeTo(200, 1);
        };

        var detailsLoaded = function(id, response) {
            toggleSpinner(false);

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
            if ('openTimes' in response 
                && 'currentWeek' in response.openTimes 
                && response.openTimes.currentWeek
            ) {
                prevBtn.unbind('click').fadeTo(200, 0);
            } else {
                prevBtn.fadeTo(200, 1);
                attachWeekNaviListener();
            }
        };

        var updateWeekNum = function(week) {
            holder.data('week-num', week);
            holder.find('.week-navi-holder .week-text .num').text(week);
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
