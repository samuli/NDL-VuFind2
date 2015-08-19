var finna = (function() {

    var my = {
        init: function() {
            // List of modules to be inited
            var modules = [
                'advSearch', 
                'bx',
                'combinedResults', 
                'common', 
                'dateRangeVis', 
                'feed', 
                'feedback', 
                'imagePopup', 
                'layout', 
                'myList', 
                'openUrl', 
                'persona', 
                'record'
            ];

            $.each(modules, function(ind, module) {
                if (typeof finna[module] !== 'undefined') {
                    finna[module].init();
                }
            });
        },
    };

    return my;
})();

$(document).ready(function() {
    finna.init();

    // Override global checkSaveStatus
    checkSaveStatuses = finna.layout.checkSaveStatuses;
});
