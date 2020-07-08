/*global VuFind, finna */
finna.dynamicList = (function finnaDynamicList() {
  var settings = {
    carousel: {
      dots: true,
      swipe: true,
      nextArrow: '<button type="button" aria-label=' + VuFind.translate("Next") + ' class="slick-next">' + VuFind.translate('Next') + '</button>',
      prevArrow: '<button type="button" aria-label=' + VuFind.translate("Prev") + ' class="slick-prev">' + VuFind.translate('Prev') + '</button>',
      lazyload: 'ondemand',
      responsive: [
        {
          breakpoint: 5000,
          settings: {
            slidesToShow: 5,
            slidesToScroll: 5
          }
        },
        {
          breakpoint: 1200,
          settings: {
            slidesToShow: 3,
            slidesToScroll: 3
          }
        },
        {
          breakpoint: 500,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 2
          }
        }
      ]
    },
    list: {
      dots: false,
      swipe: true,
      vertical: true,
      verticalSwiping: true,
      lazyload: 'ondemand',
      nextArrow: '<button type="button" aria-label=' + VuFind.translate("Next") + ' class="slick-next dynamic-btn down">' + VuFind.translate('Next') + '</button>',
      prevArrow: '<button type="button" aria-label=' + VuFind.translate("Prev") + ' class="slick-prev dynamic-btn up">' + VuFind.translate('Prev') + '</button>',
      responsive: [
        {
          breakpoint: 5000,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 2
          }
        }
      ]
    }
  };

  function handleImage(img) {
    var i = img[0];
    if (i.naturalWidth && i.naturalWidth === 10 && i.naturalHeight === 10) {
      var holder = img.closest('.dynamic-list-result, .dynamic-list-item').eq(0);
      holder.find('img, .image-wrapper, .dynamic-list-image-wrapper > a, .dynamic-list-image-wrapper > img').hide();
      holder.find('.hidden').removeClass('hidden');
    }
  }

  function initSlick() {
    $('.dynamic-list-wrapper').each(function initDynamicList() {
      $(this).one('inview', function getList() {
        var _ = $(this);
        var url = _.data('url');
        var type = _.data('type');
        if (url.length) {
          $.getJSON(VuFind.path + url).done(function parseResult(response) {
            _.append(response.data.html);
            _.find('.dynamic-list-item').each(function adjustItems() {
              var img = $(this).find('img');
              if (img.length) {
                img.on('load', function checkImage() {
                  handleImage($(this));
                });
                if (type === 'carousel') {
                  $(this).hover(function onHoverStart() {
                    var title = $(this).find('.dynamic-list-title');
                    if (!title.hasClass('active')) {
                      title.addClass('active');
                    }
                  }, function onHoverEnd() {
                    var title = $(this).find('.dynamic-list-title');
                    if (title.hasClass('active')) {
                      title.removeClass('active');
                    }
                  });
                }
              } else {
                $(this).find('.hidden').removeClass('hidden');
                img.closest('.image-wrapper').hide();
              }
            });
            _.find('.content').slick(settings[type]);
            if (type === 'list') {
              // Better positioning for buttons
              var prev = _.find('.slick-prev');
              var next = _.find('.slick-next');

              if (prev.length && next.length) {
                var wrapper = $('<div class="dynamic-btns"/>');
                wrapper.insertAfter(_.find('.slick-list'));
                wrapper.append(prev).append(next);
              }
            }
          }).fail(function onFailure() {
            // Error happened      
          });
        }
      });
    });
  }

  function initSearch() {
    $('.dynamic-list-result').each(function adjustImages() {
      var _ = $(this);
      var img = _.find('img');
      if (img.length) {
        img.unveil(100, function handleIcon() {
          $(this).on('load', function onImageLoaded() {
            handleImage($(this));
          });
        });
      } else {
        _.find('.hidden').removeClass('hidden');
      }
    });
  }

  var my = {
    init: function init() {
      initSlick();
      initSearch();
    }
  };

  return my;
})();
