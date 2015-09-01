finna.searchTabsRecommendations = (function() {

    var initSearchTabsRecommendations = function() {
        var holder = $('#search-tabs-recommendations-holder');
        if (!holder[0]) {
            return;
        }
        var url = path + '/AJAX/JSON?method=getSearchTabsRecommendations';
        var searchHash = holder.data('search-hash');
        var jqxhr = $.getJSON(url, {searchHash: searchHash}, function(response) {
            if (response.status == 'OK') {
                var holder = $('#search-tabs-recommendations-holder');
                holder.html(response.data);
                finna.layout.initTruncate(holder);
                finna.openUrl.initLinks();
                finna.layout.initSaveRecordLinks(holder);
                finna.itemStatus.initItemStatuses(holder);
                finna.itemStatus.initDedupRecordSelection(holder);
                finna.layout.checkSaveStatuses(holder);
          }
        });

    };

    var my = {
        init: function() {
            initSearchTabsRecommendations();
        }
    };

    return my;

})(finna);
