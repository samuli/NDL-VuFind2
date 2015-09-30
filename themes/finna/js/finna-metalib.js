finna.metalib = (function() {
    var page = 1;
    var loading = false;
    var fullPath = null;

    var search = function(step, set, saveHistory) {
        console.log("search: " + fullPath);
        var currentPage = 1; //metalibPage;
        var changeSet = false; //set !== null && set != metalibSet;

        // Continue only if search set was changed (whole page is reloaded) 
        // or previous Ajax load is complete
        if (!changeSet && loading) {
            return;
        }

        loading = true;

        if (step !== null) {
            page += step;
            page = Math.max(1, page);
        }

        // Load results using Ajax if: 
        //   - search set was not changed
        //   - browser supports history writing
        // Otherwise, whole page is reloaded.
        var historySupport = window.history && window.history.pushState;
        var useAJAXLoad =  true; //!metalibInited || historySupport;
        
        var replace = {};
/*
        replace.set = encodeURIComponent(set ? set : metalibSet);       
        metalibSet = replace.set;
        
        replace.page = page;        
*/
        // Tranform current url into an 'Ajaxified' version.
        // Update url parameters 'page' and 'set' if needed.
        var parts = fullPath.split('&');
        var url = parts.shift();
        if (useAJAXLoad && !changeSet) {
            url = url.replace('/Metalib/Search?', '/AJAX/JSON?');
        }
        
        for (var i=0; i<parts.length; i++) {
            var param = parts[i].split('=');
            var key = param[0];
            var val = param[1];
            if (key == 'method') {
                continue;
            }
            
            // add parameters that are included as such

            if (!(key in replace)) {
                url += '&' + key + '=' + val;
            }
        }
  /*      
        // add modified parameters
        $.each(replace, function(key, val) {
            url += '&' + key + '=' + val;
        });
    */    

        url += '&method=metaLib';

        console.log("load: " + url);
//        return;

        var holder = $('.container .results .ajax-results');        
        holder.find('.holder .row.result').remove();
;
        toggleLoading(holder, true);
        var jqxhr = $.getJSON(url, function(response) {
            toggleLoading(holder, false);
            loading = false;
            if (response.status == 'OK') {
                holder.find('.holder').html(response.data['content'] + response.data['paginationBottom']);
                $('.search-controls .pagination > div').html(response.data['paginationTop']);
                initPagination();
                
            }
        });
    };

    var toggleLoading = function(holder, mode) {
        holder.find('.loading').toggle(mode);
    };

    var initPagination = function() {
        $('ul.pagination a, ul.paginationSimple a').click(function() {
            //console.log($(this).attr("href"));
            if (!loading) {
                fullPath = $(this).attr("href") + '&method=metalib';
                search(0, '', false);
            }
            return false;
        });
    };
    
    var my = {
        init: function(path) {
            fullPath = path;
            search(0, '', false);
        }
    };

    return my;

})(finna);
