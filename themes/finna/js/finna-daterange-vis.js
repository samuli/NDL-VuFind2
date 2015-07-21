finna.dateRangeVis = (function() {
    var visNavigation = '';
    var visDateStart, visDateEnd, visMove, visRangeSelected = false;    
    var holder, searchParams = null;
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

    var timelineAction = function(action) {
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
                var newSearchParams = searchParams + '&search_sdaterange_mvfrom=' + padZeros(visDateStart) + '&search_sdaterange_mvto=' + padZeros(visDateEnd);
                finna.dateRangeVis.loadVis(action, newSearchParams);
            }

        }
    };

    var showVis = function() {
        // Display timeline when facet animation is complete
        if (openTimelineCallback) {
            fn = openTimelineCallback;
            openTimelineCallback = null;
            setTimeout(fn(), 500);
        }
    };

    var initVis = function(params, baseParams, h, start, end) {
        holder = h;

        // Save default timeline parameters
        searchParams = baseParams;
        
        if (typeof start == "undefined") {
            start = holder.find(".year-from").val();
            if (start != "") {
                start = parseInt(start, 10);
                visDateStart = start;
            }
        } else {
            visDateStart = start;            
        }

        if (typeof end == "undefined") {
            end = holder.find(".year-to").val();
            if (end != "") {
                end = parseInt(end, 10);
                visDateEnd = end;
            }
        } else {
            visDateEnd = end;
        }

        openTimelineCallback = function() { loadVis('prev', params); };
    };

    var loadVis = function(action, params) {
        // Load and display timeline (called at initial open and after timeline navigation)
        var url = path + "/AJAX/JSON" + params + "&method=dateRangeVisual";
        holder.find(".content").addClass('loading');

        $.getJSON(url, function (data) {
            if (data.status == 'OK') {
                $.each(data['data'], function(key, val) {
                    var vis = holder.find(".date-vis");
                    
                    // Get data limits
                    dataMin = parseInt(val.min, 10);
                    dataMax = parseInt(val.max, 10);
                    
                    // Compare with the values set by the user
                    if (val['min'] == 0) {
                        val['min'] = dataMin;
                    }

                    if (val['max'] == 0) {
                        val['max'] = dataMax;
                    }
                    
                    // Left & right limits have to be processed separately
                    // depending on movement direction: when reaching the left limit while
                    // zooming in or moving back, we use the max value for both
                    if ((action == 'prev' || action == 'zoom-in') && val['min'] > val['max']) {
                        val['max'] = val['min'];
                        
                        // Otherwise, we need the min value
                    } else if (action == 'next' && val['min'] > val['max']) {
                        val['min'] = val['max']; 
                    }

                    if (typeof visDateStart === 'undefined') {
                        visDateStart = parseInt(val['min'], 10);
                    }
                    
                    var maxYear = new Date().getFullYear();
                    
                    if (typeof visDateEnd === 'undefined') {
                        visDateEnd = parseInt(val['max'], 10);
                        
                        if (visDateEnd > maxYear) {
                            visDateEnd = maxYear;
                        }
                    }
                    
                    // Check for values outside the selected range and remove them
                    for (i=0; i<val['data'].length; i++) {
                        if (val['data'][i][0] < visDateStart - 5 || val['data'][i][0] > visDateEnd + 5) {
                            // Remove this
                            val['data'].splice(i,1);
                            i--;
                        }
                    }
                    
                    var options = getGraphOptions(visDateStart, visDateEnd);

                    // Draw the plot
                    var plot = $.plot(vis, [val], options);
                    var form = holder.find(".year-form");
                    var fromElement = holder.find(".year-from");            
                    var toElement = holder.find(".year-to");            

                    // Bind events
                    vis.unbind("plotclick").bind("plotclick", function (event, pos, item) {
                        if (!visRangeSelected) {
                            var year = Math.floor(pos.x);
                            fromElement.val(year);
                            toElement.val(year);
                            plot.setSelection({ x1: year , x2: year});
                        }
                        visRangeSelected = false;
                    });

                    vis.unbind("plotselected").bind("plotselected", function (event, ranges) {
                        from = Math.floor(ranges.xaxis.from);
                        to = Math.floor(ranges.xaxis.to);
                        (from != '-9999') ? fromElement.val(from) : fromElement.val();
                        (to != '9999') ? toElement.val(to) : toElement.val();
                        $('body').click();
                        visRangeSelected = true;
                    });
                    
                    
                    // Set pre-selections
                    var from = fromElement.val();
                    var to = toElement.val();

                    var preFromVal = from ? from : val['min'];
                    var preToVal = to ? to : val['max'];

                    if (to || from) {
                        plot.setSelection({ x1: preFromVal , x2: preToVal});
                    } 
                    
                    vis.closest(".content").removeClass('loading');                    
                });
            }
        });
    };

    var getGraphOptions = function(visDateStart, visDateEnd) {
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
                min: visDateStart,
                max: visDateEnd,
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

    var initTimelineNavigation = function() {
        $('#side-panel-search_sdaterange_mv .navigation div').on(
            "click", 
            {callback: timelineAction}, 
            function(e) {
                e.data.callback($(this).attr('class').split(' ')[0]);
            }
        );
    };

    var initUI = function() {
        // Override default facet open/close behavior
        var facet = $("#side-panel-search_sdaterange_mv");
        var title = facet.find(".title");
        title.on("click", function(e) {
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
        });

        facet.find(".year-form").each(function() {
            initForm($(this));
        });
    };
    
    var initForm = function(form) {
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
            var type = $(this).find('input[type=radio][name=search_sdaterange_mvtype]:checked').val();
            var query = action;
            query += "sdaterange[]=search_sdaterange_mv"
            query += "&search_sdaterange_mvtype=" + type + "&";
            
            
            fromElement.removeClass('invalidField');
            toElement.removeClass('invalidField');
            
            // Require numerical values
            if (!isNaN(from) && from != "" || !isNaN(to) && to != "") {
                if (from == '' && to == '') { // both dates empty; use removal url
                    query = action;
                } else if (from == '') { // only start date set
                    if (type == 'within') {
                        fromElement.addClass('invalidField');
                        return false;
                    }
                    query += 'search_sdaterange_mvto=' + padZeros(to);
                } else if (to == '')  { // only end date set
                    if (type == 'within') {
                        toElement.addClass('invalidField');
                        return false;
                    }
                    query += 'search_sdaterange_mvfrom=' + padZeros(from);
                } else { // both dates set
                    query += 'search_sdaterange_mvfrom=' + padZeros(from) + '&search_sdaterange_mvto=' + padZeros(to);
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
        initUI();
        initTimelineNavigation();
    };

    var my = {
        init: init,
        loadVis: loadVis,
        initVis: initVis
    };

    return my;

})(finna);
