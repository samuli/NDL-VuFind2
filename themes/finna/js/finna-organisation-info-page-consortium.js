/*global VuFind*/
finna = $.extend(finna, {
    organisationInfoPageConsortium: function() {
        var consortiumInfo = false;
        var holder = false;

        var updateConsortiumNotification = function(data) {
            if ('consortium' in data) {
                if ('finna' in data.consortium
                    && 'notification' in data.consortium.finna
                   ) {
                       holder.find('.consortium-notification')
                           .html(data.consortium.finna.notification).removeClass('hide');
                   }
            }
        };

        var updateConsortiumInfo = function(data, organisationList) {
            var showConsortiumName = false;
            var info = holder.find('.consortium-info');
            var usageInfo = holder.find('.consortium-usage-rights').removeClass('hide');
            holder.find('.consortium-navigation').removeClass('hide');

            var consortiumHomepage = null;
            var consortiumHomepageLabel = null;

            // Info
            if ('consortium' in data) {
                var consortiumData = data.consortium;
                console.log("cons: %o", consortiumData);

                consortiumHomepage = finna.common.getField(consortiumData, 'homepage');
                consortiumHomepageLabel = finna.common.getField(consortiumData, 'homepageLabel');

                var desc = finna.common.getField(consortiumData, 'description');
                if (desc) {
                    showConsortiumName = true;
                    info.find('.description').html(desc).removeClass('hide');
                }
                var logo = null;
                if ('logo' in consortiumData) {
                    showConsortiumName = true;                
                    logo = finna.common.getField(consortiumData.logo, 'small');
                    $('<img/>').attr('src', logo).appendTo(info.find('.consortium-logo').removeClass('hide'));
                } else {
                    info.addClass('no-logo');
                }

                if (showConsortiumName) {
                    var name = finna.common.getField(consortiumData, 'name');
                    if (name) {
                        info.removeClass('hide').find('.name').text(name);
                        enableConsortiumNaviItem('building');
                    }
                }

                var finnaData = finna.common.getField(consortiumData, 'finna');
                if (finnaData) {
                    var usage = finna.common.getField(finnaData, 'usage_info');
                    if (usage) {
                        usageInfo.find('.usage-rights-text').html(usage);
                    }
                    
                    var usagePerc = finna.common.getField(finnaData, 'usage_perc');        
                    if (usagePerc) {
                        // Gauge
                        usageInfo.find('.gauge-meter').removeClass('hide');

                        var opts = {
                            lines: 0,
                            angle: 0.1,
                            lineWidth: .09,
                            limitMax: 'true',
                            colorStart: '#00A2B5',
                            colorStop: '#00A2B5',
                            strokeColor: '#e5e5e5',
                            generateGradient: true
                        };
                        var target = holder.find('.finna-coverage-gauge')[0];

                        var gauge = new Donut(target).setOptions(opts);
                        gauge.maxValue = 100;
                        gauge.animationSpeed = 10;

                        var gaugeVal = usagePerc*100;
                        gauge.set(gaugeVal);
                        holder.find('.gauge-value .val').text(Math.round(gaugeVal));
                    }
                    if (usage || usagePerc) {
                        holder.find('.usage-rights-heading').removeClass('hide');
                        enableConsortiumNaviItem('usage');
                    }

                    var links = finna.common.getField(finnaData, 'links');        
                    if (links) {
                        var linksHolder = holder.find('.consortium-usage-rights .links');
                        linksHolder.removeClass('hide');
                        var template = linksHolder.find('li.template').removeClass('template');
                        $(links).each(function(ind, obj) {
                            var li = template.clone();
                            var a = li.find('a');
                            a.attr('href', obj.value).text(obj.name);
                            li.appendTo(linksHolder);
                        });
                        template.remove();
                    }
                }
            }

            if (consortiumHomepage) {
                var label = consortiumHomepageLabel ? consortiumHomepageLabel : consortiumHomepage;
                var linkHolder = holder.find('.consortium-info .homepage').removeClass('hide');
                $('<a/>').attr('href', consortiumHomepage).text(label).appendTo(linkHolder);
            }

            // Organisation list
            var list = false;
            var listHolder = info.find('.organisation-list');
            var ul = listHolder.find('ul');
            $.each(organisationList, function(id, obj) {
                if (obj.type == 'facility') {
                    var name = obj.name;
                    if ('shortName' in obj) {
                        name = obj.shortName;
                    }
                    var li = $('<li/>');
                    var homepage = finna.common.getField(obj, 'homepage');
                    if (name && homepage) {
                        list = true;
                        $('<a/>').attr('href', homepage).text(name).appendTo(li);
                        li.appendTo(ul);
                    }
                }
            });

            if (list) {
                listHolder.addClass('truncate-field');
                finna.layout.initTruncate(listHolder.parent());
            }
        };

        var enableConsortiumNaviItem = function(id) {
            holder.find('.consortium-navigation .scroll.' + id).addClass('active');
        };
        
        var initConsortiumNavi = function() {
            var active  = holder.find('.consortium-navigation .scroll.active');
            if (active.length > 1) {
                active.removeClass('hide');

                var sections = holder.find('.navi-section');
                holder.find('.consortium-navigation-list .scroll').each(function(ind) {
                    $(this).click(function() {
                        $('html, body').animate({
                            scrollTop: $(sections[ind]).offset().top-40
                        }, 200);
                    });
                });
            } else {
                holder.find('.consortium-navigation').hide();
            }
        };

        var my = {
            enableConsortiumNaviItem: enableConsortiumNaviItem,
            initConsortiumNavi: initConsortiumNavi,
            updateConsortiumInfo: updateConsortiumInfo,
            init: function(_holder) {
                holder = _holder;
            }
        };

        return my;
    }
});
