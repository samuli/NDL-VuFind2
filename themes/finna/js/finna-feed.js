finna.feed = (function() {
    var calculateScrollSpeed = function(scrollCnt) {
        return 750 * Math.max(1, (scrollCnt/5));
    };

var adjustImageOffsets = function(holder) {
 holder.find(".carousel-feed:not(.slick-vertical) .slick-slide .wrapper img").each (function() {
    adjustImageOffset($(this));
 });
};

    var adjustImageOffset = function(img) {
                                var offset = img.width() - img.closest(".slick-slide").width();
                                if (offset > 0) {
                                    img.css("margin-left", "-" + offset/2 + "px");                                
                                }
                                else {
                                    img.css("margin-left", "auto");
                                }
    };
    
    var adjustWidth = function(holder) {
        holder.find(".carousel-slide-header p, .carousel-text")
            .width(holder.find(".slick-slide").width()-20);
             holder.find(".slick-slide .wrapper img").each (function() {
                adjustImageOffset($(this));
             });
    };

    var loadFeed = function(holder) {        
        var id = holder.data('feed');
        if (typeof(id) == "undefined") {
            return;
        }

        var url = path + '/AJAX/JSON?method=getFeed&id=' + id;
        $.getJSON(url, function(response) {
            if (response.status == 'ERROR') {
                holder.html('<div class="error">' + vufindString.error + '</div>');
                return;
            }

            if (response.status === 'OK' && response.data) {
                holder.html(response.data.html);
                var settings = response.data.settings;
                if (settings.type == "carousel") {
                    

                    holder.find(".carousel-feed").slick({
                        dots: settings['dots'],
                        swipe: true,
                        infinite: true,
                        touchThreshold: 8,
                        autoplay: settings['autoplay'],
                        autoplaySpeed: settings['autoplay'],
                        slidesToShow: settings['slidesToShow']['desktop'],
                        slidesToScroll: settings['scrolledItems']['desktop'],
                        speed: calculateScrollSpeed(settings['scrolledItems']['desktop']),
                        vertical: settings['vertical'],
                        responsive: responsive = [
                        {
                            breakpoint: 1200,
                            settings: {
                                slidesToShow: settings['slidesToShow']['desktop-small'],
                                slidesToScroll: settings['scrolledItems']['desktop-small'],
                                speed: calculateScrollSpeed(settings['scrolledItems']['desktop-small']),                                
                                infinite: true,
                                dots: true
                            }
                        },
                        {
                            breakpoint: 992,
                            settings: {
                                slidesToShow: settings['slidesToShow']['tablet'],
                                slidesToScroll: settings['scrolledItems']['tablet'],
                                speed: calculateScrollSpeed(settings['scrolledItems']['tablet']),                                
                                infinite: true,
                                dots: true
                            }
                        },
                        {
                            breakpoint: 768,
                            settings: {
                                slidesToShow: settings['slidesToShow']['mobile'],
                                slidesToScroll: settings['scrolledItems']['mobile'],
                                speed: calculateScrollSpeed(settings['scrolledItems']['mobile']),
                                infinite: true,
                                dots: true
                            }
                        }
                        ]
                    });
                    
                    if (settings['type'] == 'carousel' && !settings['vertical']) {
                        adjustWidth(holder);
                        $(window).resize(function() {
                            setTimeout(function() { adjustWidth(holder);}, 250);
                            
                            if (typeof(settings['height']) != 'undefined') {
                                adjustImageOffsets(holder);
                                
                             }
                             
                            
                        });
                    }

                    if (typeof(settings['height']) != 'undefined') {
                        holder.find(".slick-slide").css("max-height", settings['height'] + "px");
                        holder.find(".slick-slide").addClass('adjusted-height');
                        holder.find(".slick-track, .slick-slide .wrapper").css("max-height", settings['height'] + "px");
                        holder.find(".slick-slide .wrapper img").css("height", settings['height'] + "px");
                        holder.find(".carousel-feed:not(.slick-vertical) .slick-slide .wrapper img").each (function() {
                            $(this).on("load", function() {
                                adjustImageOffset($(this));
                            });
                        }); 
                    }
                }
            }
        });        
    };
    
    var initComponents = function() {
        $(".feed-container").each(function() {
            loadFeed($(this));
        });
    };

    var my = {
        init: function() {
            initComponents();
        }
    };

    return my;

})(finna);
