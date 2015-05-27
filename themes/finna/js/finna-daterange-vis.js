finna.dateRangeVis = (function() {

    var initVis = function() {
        
        var url = path + "/AJAX/JSON?method=dateRangeVisual";

        $.getJSON(url, function(response) {
            if (response.status === 'OK' && response.data) {
            
            }
        });
    };

    var my = {
        init: function() {
            initVis();
        }
    };

    return my;

})(finna);
