finna.combinedResults = (function() {

    var my = {
        init: function(holder) {
            finna.layout.initTruncate();
            finna.openUrl.initLinks(holder);
            finna.openUrl.triggerAutoLoad();
            finna.layout.initSaveRecordLinks(holder);
        },
    };

    return my;
})(finna);
