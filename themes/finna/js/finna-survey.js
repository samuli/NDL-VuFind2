/*global VuFind*/
finna.survey = (function() {
    var cookieName = 'finnaSurvey';

    var init = function() {
        console.log("init");
        var cookie = $.cookie(cookieName);
        if (typeof cookie !== 'undefined' && cookie == '1') {
            return;
        }

        var holder = $('#survey');
        holder.find('a').click(function(e) {
            holder.fadeOut(100);
            $.cookie(cookieName, 1, { path: '/' });
            
            if ($(this).hasClass('close-survey')) {
                return false;
            } 
        });

        setTimeout(function() {
            holder.fadeIn(300).css({bottom: 0});
        }, 150);
    };

    var my = {
        init: init
    };

    return my;
})(finna);
