finna.metalib = (function() {
    var page = 1;
    var loading = false;
    var originalPath = currentPath = null;
    var searchSet = null;

    var search = function(fullPath, step, saveHistory) {
        // Continue only if search set was changed (whole page is reloaded) 
        // or previous Ajax load is complete
        if (loading) {
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
        replace.set = searchSet; //encodeURIComponent(set ? set : metalibSet);       
        //replace.page = page;        

/*
        metalibSet = replace.set;
        
        replace.page = page;        
*/
        // Tranform current url into an 'Ajaxified' version.
        // Update url parameters 'page' and 'set' if needed.
        var parts = fullPath.split('&');
        var url = parts.shift();
        if (useAJAXLoad) {
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
        
        // add modified parameters
        $.each(replace, function(key, val) {
            url += '&' + key + '=' + val;
        });
        

        url += '&method=metaLib';

        currentPath = url;

        console.log("load: " + url);

        var holder = $('.container .results .ajax-results');        
        holder.find('.holder .row.result').remove();

        var parent = this;
        toggleLoading(holder, true);
        var jqxhr = $.getJSON(url, function(response) {
            toggleLoading(holder, false);
            loading = false;
            if (response.status == 'OK') {
                var hash = response.data['searchHash'];
                initTabNavigation(hash);
                holder.find('.holder').html(response.data['content'] + response.data['paginationBottom']);
                $('.search-controls .pagination > div').html(response.data['paginationTop']);
                initPagination();
                finna.layout.init();
                finna.openUrl.initLinks();
            }
        });


        // Save history if supported
        if (saveHistory && historySupport) {
            var state = {page: page};
            if (searchSet) {
                state.set = searchSet;
            }
            var title = '';
            // Restore ajaxified URL before saving history
            var tmp = url.replace('/AJAX/JSON?', '/Metalib/Search?');
            tmp = tmp.replace('&method=metaLib', '');
            window.history.pushState(state, title, tmp);
        }

    };

    var toggleLoading = function(holder, mode) {
        holder.find('.loading').toggle(mode);
    };

    var initPagination = function() {
        $('ul.pagination a, ul.paginationSimple a').click(function() {
            if (!loading) {
                path = $(this).attr("href") + '&method=metalib';
                search(path, 0, true);
            }
            return false;
        });
    };

    var initSetChange = function() {
        $(".search-sets input").on("click", function() { 
            var parts = originalPath.split('&');
            var url = parts.shift();
                    
            for (var i=0; i<parts.length; i++) {
                var param = parts[i].split('=');
                var key = param[0];
                var val = param[1];
                if (key == 'page' || key == 'set') {
                    continue;
                }
                url += '&' + key + '=' + val;                
            }
            url += '&set=' + $(this).val();
            location = url;
        });
    };

    var initHistoryNavigation = function() {
        window.onpopstate = function(e){
            if (e.state){
                search(document.location.href, null, false);
            }
        };
    };

    var initTabNavigation = function(hash) {
        $(".nav-tabs li a").click(function() {
            var href = $(this).attr('href');
            href += ('&search[]=Metalib:' + hash);
            $(this).attr('href', href);
        });
    };
    
    var my = {
        init: function(set, path) {
            searchSet = set;
            originalPath = path;

            initSetChange();
            initHistoryNavigation();

            search(path, 0, true);
        }
    };

    return my;

})(finna);
