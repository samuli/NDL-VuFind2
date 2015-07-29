finna.dateRangeVis = (function() {
    var loading = firstLoad = true;
    var visNavigation = '';
    var visData = visDateStart = visDateEnd = visMove = visRangeSelected = plotStart = plotEnd = false;    
    var dataMin = dataMax = disableTimeline = false;
    var holder = searchParams = facetField = facetOperator = currentDevice = null;
    var openTimelineCallback = null;

    // Move dates: params either + or -
    var moveVis = function(start, end) {
        var ops = {
            '+': function(a) { return a += visMove },
            '-': function(a) { return a -= visMove }
        };
        visDateStart = ops[start](visDateStart);
        visDateEnd = ops[end](visDateEnd);
    };

    var timelineAction = function(backend, action) {
        if (loading) {
            return;
        }
        plotStart = plotEnd = false;

        // Navigation: prev, next, out or in
        if (typeof action != 'undefined') {
            // Require numerical values
            if (!isNaN(visDateStart) && !isNaN(visDateEnd)) {
                visMove = Math.ceil((visDateEnd - visDateStart) * .2);
                if (visMove < 1) visMove = 1; // Require >= 1 year movements
                
                // Changing the dates using the moveVis function above
                if (action == 'prev') {
                    moveVis('-','-');
                } else if (action == 'next') {
                    moveVis('+','+');
                } else if (action == 'zoom-out') {
                    moveVis('-','+');
                } else if (action == 'zoom-in') {
                    
                    // Only allow zooming in if years differ
                    if (visDateStart != visDateEnd) {
                        moveVis('+','-');
                    }
                }
                
                // Make sure start <= end
                if (visDateStart > visDateEnd) {
                    visDateStart = visDateEnd;
                }

                // Create the string of date params


                var newSearchParams = searchParams + '&filter[]=' + facetOperator + facetField + ':[' + padZeros(visDateStart) + ' TO ' + padZeros(visDateEnd) + ']';
                visData = null;
                finna.dateRangeVis.loadVis(backend, action, newSearchParams);
            }
        }
    };

    var showVis = function() {
        // Display timeline when facet animation is complete
        if (openTimelineCallback) {
            fn = openTimelineCallback;
            openTimelineCallback = null;
            setTimeout(fn, 500);
        } else if (!disableTimeline) {
            plotData();
        }
    };

    var initVis = function(backend, facet, operator, 
                           params, baseParams, h, start, end, plotImmediately) {        
        facetField = facet;
        facetOperator = operator;
        holder = h;

        // Save default timeline parameters
        searchParams = baseParams;
        
        var field = holder.find(".year-from");
        if (field.length) {
            startVal = field.val();
            if (startVal != "") {
                visDateStart = parseInt(startVal, 10);
            }
        }

        if (typeof start != undefined && start !== false) {
            plotStart = start;
        }

        if (visDateStart === false) {
            visDateStart = start;
        }

        field = holder.find(".year-to");
        if (field.length) {
            endVal = field.val();
            if (endVal != "") {
                visDateEnd = parseInt(endVal, 10);
            }
        }

        if (typeof end != undefined && end !== false) {
            plotEnd = end;
        }

        if (visDateEnd === false) {
            visDateEnd = end;
        }


        initTimelineNavigation(backend, h);
        h.closest(".daterange-facet").find(".year-form").each(function() {
            initForm($(this), backend, facet);
        });

        openTimelineCallback = function() { loadVis(backend, 'prev', params); };
        if (typeof plotImmediately != "undefined" && !plotImmediately) {
            // Callback for loading visualization when timeline is opened
            openTimelineCallback = function() { loadVis(backend, 'prev', params); };

            if (backend == 'primo') {
                // Load visialization data immediately to get data limits (start/end years). 
                // We'll need this to build (fake) open-interval queries, which Primo does not support.
                setTimeout(openTimelineCallback, 10);
            }
        } else {
            openTimelineCallback();
        }
    };

    var loadVis = function(backend, action, params) {
        if (backend == 'primo' && visData) {
            // Primo visualization data is loaded before timeline is opened.            
            plotData();
            return;
        }
        
        // Load and display timeline (called at initial open and after timeline navigation)
        var url = path + "/AJAX/JSON" + params + "&method=dateRangeVisual&backend=" + backend;
        holder.find(".content").addClass('loading');
        
        loading = true;
        $.getJSON(url, function (data) {
            loading = false;
            var vis = holder.find(".date-vis");
            vis.closest(".content").removeClass('loading');
            if (data.status == 'OK') {
                $.each(data['data'], function(key, val) {
                    // Get data limits
                    dataMin = parseInt(val.min, 10);
                    dataMax = parseInt(val.max, 10);

                    val['min'] = dataMin;
                    val['max'] = dataMax;
                    
                    // Left & right limits have to be processed separately
                    // depending on movement direction: when reaching the left limit while
                    // zooming in or moving back, we use the max value for both
                    if ((action == 'prev' || action == 'zoom-in') && val['min'] > val['max']) {
                        val['max'] = val['min'];
                        
                        // Otherwise, we need the min value
                    } else if (action == 'next' && val['min'] > val['max']) {
                        val['min'] = val['max']; 
                    }

                    
                    if (visDateStart === false || (firstLoad && dataMin > visDateStart)) {                        
                        visDateStart = dataMin;
                    }
                    
                    if (visDateEnd === false || (firstLoad && dataMax < visDateEnd)) {
                        visDateEnd = dataMax;
                    }

                    if (firstLoad) {
                        visDateEnd = Math.min(visDateEnd, new Date().getFullYear());
                    }
                    
                    // Check for values outside the selected range and remove them
                    for (i=0; i<val['data'].length; i++) {
                        if (val['data'][i][0] < visDateStart - 5 || val['data'][i][0] > visDateEnd + 5) {
                            // Remove this
                            val['data'].splice(i,1);
                            i--;
                        }
                    }
                    
                    visData = val;
                    plotData();
                });
                firstLoad = false;
            }
        });
    };

    var plotData = function() {
        var plotVisible = holder.is(":visible");
        if (!plotVisible) {
            return;
        }

        var start = visDateStart;
        if (plotStart != false && plotStart > visDateStart) {
            start = plotStart;
        }

        var end = visDateEnd;
        if (plotEnd != false && plotEnd < visDateEnd) {
            end = plotEnd;
        }
        console.log("plot: " + start + "-" + end + ", plotStart: " + plotStart + "-" + plotEnd + ", visDateStart: " + visDateStart + "-" + visDateEnd);

        var options = getGraphOptions(start, end);
        var vis = holder.find(".date-vis");

        // Draw the plot
        var plot = $.plot(vis, [visData], options);
        var form = holder.find(".year-form");
        var fromElement = holder.find(".year-from");            
        var toElement = holder.find(".year-to");            
        var plotInited = false;

        // Bind events
        vis.unbind("plotclick").bind("plotclick", function (event, pos, item) {
            if (!visRangeSelected) {
                var year = Math.floor(pos.x);
                plot.setSelection({ x1: year , x2: year+1});
                fromElement.val(year);
                toElement.val(year);
            }
            visRangeSelected = false;
        });

        vis.unbind("plotselected").bind("plotselected", function (event, ranges) {
            visRangeSelected = true;

            if (!plotInited) {
                return;
            }
            from = Math.floor(ranges.xaxis.from);
            to = Math.floor(ranges.xaxis.to);
            if (from != '-9999') {
                fromElement.val(from);
            }
            if (to != '-9999') {
                toElement.val(to);
            }

            $('body').click();
        });
        
        // Set pre-selections
        var from = fromElement.val();
        var to = toElement.val();

        if (from || to) {
            from = from ? from : visData['min'];
            to = to ? to : visData['max'];
            plot.setSelection({ x1: from , x2: to});
        }
        plotInited = true;
    };

    var getGraphOptions = function(start, end) {
        var options =  {
            series: {
                bars: { 
                    show: true,
                    color: "#00a3b5",
                    fillColor: "#00a3b5"
                }
            },
            colors: ["#00a3b5"],
            legend: { noColumns: 2 },
            xaxis: { 
                min: start,
                max: end,
                tickDecimals: 0,                         
                font :{
                    size: 13,
                    family: "'helvetica neue', helvetica,arial,sans-serif",
                    color:'#464646',
                    weight:'bold'
                }                  
            },
            yaxis: { min: 0, ticks: [] },
            grid: { 
                backgroundColor: null, 
                borderWidth:0,
                axisMargin:0,
                margin:0,
                clickable:true
            }
        };

        // Disable selection of time range (by dragging) when on Android
        // (otherwise the timeline component doesn't always get redrawn 
        // correctly after a selection has been made.) 
        var isAndroid = navigator.userAgent.match(/(android)/i);
        if (!isAndroid) {
            options['selection'] = {mode: "x", color:'#00a3b5;', borderWidth:0};
        }

        
        return options;
    };

    var initTimelineNavigation = function(backend, holder) {
        holder.find('.navigation div').on(
            "click", 
            {callback: timelineAction}, 
            function(e) {
                e.data.callback(backend, $(this).attr('class').split(' ')[0]);
            }
        );
    };

    var initFacetBar = function() {
        var facet = $(".daterange-facet");
        var title = facet.find(".title");
        title.on("click", function(e) {
            if (!disableTimeline) {
                // Override default facet open/close behavior when 
                // timeline is enabled
                var facet = $(this).closest(".facet");
                var facetItem = facet.find(".list-group-item");
                var collapsed = facetItem.hasClass("collapsed");
                
                if (e.offsetX > title.outerWidth()-30) {
                    // :after icon clicked > open/close facet
                    if (!collapsed) {
                        facet.toggleClass("wide", false);
                    } else if (facet.hasClass("timeline")) {
                        facet.toggleClass("wide", true);                    
                    }
                } else {
                    // Facet title clicked
                    facet.toggleClass("wide");
                    if (collapsed) {
                        if (!facet.hasClass("timeline")) {
                            facet.toggleClass("timeline", true);
                            showVis();
                        }
                    } else {
                        facet.toggleClass("timeline");
                        if (facet.hasClass("timeline")) {
                            showVis();
                        }
                        return false;                
                    }
                }
            }
        });
    };
    
    var initResizeListener = function() {
        disableTimeline = finna.layout.getDevice() == "xs";
        $(window).on("device-change.screen.finna", function(e, data) {
            var device = data.device;
            var prevDevice = data.prevDevice;
            disableTimeline = device == "xs";
            if (disableTimeline) {
                // disable timeline
                var facet = $(".daterange-facet");
                facet.toggleClass("wide", false);
                facet.toggleClass("timeline", false);
            } else {
                plotData();
            }
        });
    };

    var initForm = function(form, backend, facetField) {
        form.find("a.submit").on("click", 
           function() {
               $(this).closest("form").submit();
               return false;
           }
        );

        form.submit(function(e) {
            e.preventDefault();

            // Get dates, build query
            var fromElement = $(this).find(".year-from");            
            var from = fromElement.val();
            
            var toElement = $(this).find(".year-to");
            var to = toElement.val();
            
            var action = $(this).attr('action');
            if (action.indexOf("?") < 0) {
                action += '?'; // No other parameters, therefore add ?
            } else {
                action += '&'; // Other parameters found, therefore add &
            }

            var query = action;
            var isSolr = backend == "solr";


            var type = null;
            if (isSolr) {
                type = $(this).find('input[type=radio][name=type]:checked');
                if (type.length) {
                    type = type.val();
                    query += facetField + "_type=" + type + "&";
                }
            }
                
            fromElement.removeClass('error');
            toElement.removeClass('error');

            query += 'filter[]=' + facetOperator + facetField + ':';
            
            // Require numerical values
            if (!isNaN(from) && !isNaN(to)) {
                if (from == '' && to == '') { // both dates empty; use removal url
                    query = action;
                } else if (from == '') { // only end date set
                    if (type == 'within') {
                        fromElement.addClass('error');
                        return false;
                    }

                    query += '"[';
                    query += isSolr ? '*' : dataMin !== false ? dataMin : 0;
                    query += '+TO+' + padZeros(to) + ']"';
                } else if (to == '')  { // only start date set
                    if (type == 'within') {
                        toElement.addClass('error');
                        return false;
                    }

                    query += '"[' + padZeros(from) + ' TO ';                    
                    query += isSolr ? '*' : dataMax !== false ? dataMax : 9999;
                    query += ']"';
                } else if (from > to) {
                    fromElement.addClass("error");
                    toElement.addClass('error');
                    return false;                    
                } else { // both dates set
                    query += '"['+padZeros(from)+' TO '+padZeros(to)+']"';
                }

                // Perform the new search
                window.location = query;
            }
            return;
        });
    };
    
    var padZeros = function(number, length) {
        if (typeof length == 'undefined') {
            length = 4;
        }
        // Room for any leading negative sign
        var negative = false;
        if (number < 0) {
            negative = true;
            number = Math.abs(number);
        }
        var str = '' + number;
        while (str.length < length) {
            str = '0' + str;
        }
        return (negative ? '-' : '') + str;
    } 

    var init = function() {
        initFacetBar();
        initResizeListener();
    };

    var my = {
        init: init,
        loadVis: loadVis,
        initVis: initVis
    };

    return my;

})(finna);
