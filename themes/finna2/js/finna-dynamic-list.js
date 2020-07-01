/*global VuFind, finna */
finna.dynamicList = (function finnaDynamicList() {
  var settings = {
    carousel: {
      dots: true,
      swipe: true,
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
      dots: true,
      swipe: true,
      vertical: true,
      lazyload: 'ondemand',
      nextArrow: $('.dynamic-list-btn.down'),
      prevArrow: $('.dynamic-list-btn.up'),
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
      img.hide();
      img.closest('.image-wrapper').hide();
      var parent = img.closest('.dynamic-list-item');
      parent.find('.hidden').removeClass('hidden');
      parent.find('.dynamic-list-title').addClass('no-image');
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
              } else {
                $(this).find('.hidden').removeClass('hidden');
                img.closest('.image-wrapper').hide();
                $(this).find('.dynamic-list-title').addClass('no-image');
              }
              if (type === 'carousel') {
                img.hover(function hoverStart() {
                  $(this).siblings('.dynamic-list-title').css('opacity', '1');
                },
                function hoverEnd() {
                  $(this).siblings('.dynamic-list-title').css('opacity', '0');
                });
              }
            });       
            _.find('.content').slick(settings[type]);
          }).fail(function onFailure() {
                      
          });
        }
      });
    });
  }

  function initSearch() {
    $('.dynamic-list-result').each(function adjustImages() {
      var img = $(this).find('img');
      if (img.length) {
        img.unveil(100, function handleIcon() {
          $(this).on('load', function onImageLoaded() {
            handleImage($(this));
          });
        });
      } else {
        $(this).find('.hidden').removeClass('hidden');
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
