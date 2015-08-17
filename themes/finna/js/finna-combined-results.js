finna.combinedResults = (function() {

    var my = {
        init: function(holder) {
            finna.layout.initTruncate();
            finna.openUrl.initLinks(holder);
            finna.openUrl.triggerAutoLoad();
            finna.record.initSaveRecordLinks(holder);
            finna.record.checkSaveStatuses(holder);
        },
    };

    return my;
})(finna);
