finna.dateRangeVis = (function() {
    var visNavigation = '', visDateStart, visDateEnd, visMove, visRangeSelected = false;
    
    var pageReadyCb = null;

    // Move dates: params either + or -
    var moveVis = function(start, end) {
        var ops = {
            '+': function(a) { return a += visMove },
            '-': function(a) { return a -= visMove }
        };
        visDateStart = ops[start](visDateStart);
        visDateEnd = ops[end](visDateEnd);
    };

    var loadVisNow = function(
        holder, action, filterField, facetField, 
        searchParams, url, 
        collection, collectionAction
    ) {
        holder.addClass('loading');



        // Navigation: prev, next, out or in
        if (typeof action == 'undefined') {
            // Require numerical values
            if (!isNaN(visDateStart) && !isNaN(visDateEnd)) {
                visMove = Math.ceil((visDateEnd - visDateStart) * .2);
                if (visMove < 1) visMove = 1; // Require >= 1 year movements
                
                // Changing the dates using the moveVis function above
                if (action == 'prev') {
                    moveVis('-','-');
                } else if (action == 'next') {
                    moveVis('+','+');
                } else if (action == 'zoomOut') {
                    moveVis('-','+');
                } else if (action == 'zoomIn') {
                    
                    // Only allow zooming in if years differ
                    if (visDateStart != visDateEnd) {
                        moveVis('+','-');
                    }
                }
                
                // Make sure start <= end
                if (visDateStart > visDateEnd) {
                    visDateStart = visDateEnd;
                }
                
                // TODO: PCI
                // TODO: collection
                // TODO: advanced haku

                // Create the string of date params
                searchParams = 'filterField=search_sdaterange_mv&facetField=main_date_str&sdaterange[]=' + filterField + '&' + filterField + 'from=' + this.padZeros(visDateStart) + '&' + filterField + 'to=' + this.padZeros(visDateEnd); 
                    //+'&{$searchParamsWithoutFilter|escape:'javascript'}';          
            }
        } else {
            //searchParams = '{$searchParams|escape:'javascript'}'; 
        }

        this.pageReadyCb = function() {
            loadVis(
                holder, action, filterField, facetField, 
                searchParams, url
            );
            initUI(holder);
        };
    };

    var loadVis = function(
        holder, action, filterField, facetField, 
        searchParams, baseURL
    ) {
        
        var url = path + "/AJAX/JSON" + searchParams + "&method=dateRangeVisual";

        $.getJSON(url, function (data) {
            if (data.status == 'OK') {
                $.each(data['data'], function(key, val) {
                    var vis = holder.find(".date-vis");
                    
                    // Get data limits
                    dataMin = parseInt(val['data'][0][0], 10);
                    dataMax = parseInt(val['data'][val['data'].length - 1][0], 10);
                    
                    // National home page: render default view from 1800 to present
                    /*
                    if (typeof visNationalHome !== 'undefined' && visNationalHome) {
                        dataMin = 1800;
                    }*/

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
                    if ((action == 'prev' || action == 'zoomIn') && val['min'] > val['max']) {
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
                   

                    console.log("options: %o", options);

                    // Draw the plot
                    var plot = $.plot(vis, [val], options);

  

                    
                    // Bind events
                    vis.unbind("plotclick").bind("plotclick", function (event, pos, item) {
                        if (!visRangeSelected) {
                            var year = Math.floor(pos.x);
                            $('#mainYearFrom, #mainYearTo').val(year);
                            plot.setSelection({ x1: year , x2: year});
                        }
                        visRangeSelected = false;
                    });

                    vis.unbind("plotselected").bind("plotselected", function (event, ranges) {
                        from = Math.floor(ranges.xaxis.from);
                        to = Math.floor(ranges.xaxis.to);
                        (from != '-9999') ? $('#mainYearFrom').val(from) : $('#mainYearFrom').val();
                        (to != '9999') ? $('#mainYearTo').val(to) : $('#mainYearTo').val();
                        $('body').click();
                        visRangeSelected = true;
                    });
                    
                    
                    // Set pre-selections
/*
                    var preFromVal = ($('#mainYearFrom').val()) ? $('#mainYearFrom').val() : val['min'];
                    var preToVal = ($('#mainYearTo').val()) ? $('#mainYearTo').val() : val['max'];

                    if ($('#mainYearFrom').val() || $('#mainYearTo').val()) {
                        plot.setSelection({ x1: preFromVal , x2: preToVal});
                    } 
  */                  
                    vis.removeClass('loading');                    
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
                    color:'#fff',
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

    var initUI = function(holder) {
        console.log("initUI");
        // Timeline search functionality
        var form = holder.find(".year-form");
        form.submit(function(e) {            
            e.preventDefault();
            // Get dates, build query
            var fromElement = $(this).find("#year-from");            
            var from = fromElement.val();
            
            var toElement = $(this).find("#year-to");
            var to = toElement.val();

            console.log("from: " + from + "-" + to);

            var action = $(this).attr('action');
            if (action.indexOf("?") < 0) {
                action += '?'; // No other parameters, therefore add ?
            } else {
                action += '&'; // Other parameters found, therefore add &
            }
            var type = $(this).find('input[name=search_sdaterange_mvtype]:checked').val();
            var query = action + 'sdaterange[]=search_sdaterange_mv&search_sdaterange_mvtype=' + type + '&';
            
            fromElement.removeClass('invalidField');
            toElement.removeClass('invalidField');
            
            // Require numerical values
            if (!isNaN(from) && !isNaN(to)) {
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
//                console.log(query);
                window.location = query;
            }
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
        if (this.pageReadyCb) {
            this.pageReadyCb();
        }
    };

    var my = {
        init: init,
        loadVis: loadVis,
        loadVisNow: loadVisNow
    };

    return my;

})(finna);
