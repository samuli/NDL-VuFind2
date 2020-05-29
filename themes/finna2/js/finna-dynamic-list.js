/*global VuFind, finna */
finna.dynamicList = (function finnaDynamicList() {
  var settings = {
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
  };

  function handleImage(img) {
    var i = img[0];
    if (i.naturalWidth && i.naturalWidth === 10 && i.naturalHeight === 10) {
      img.hide();
      img.siblings('.hidden').removeClass('hidden');
      img.siblings('.dynamic-list-title').addClass('no-image');
    }
  }

  function initSlick() {
    $('.dynamic-list-wrapper').each(function initDynamicList() {
      $(this).one('inview', function getList() {
        var _ = $(this);
        var url = _.data('url');
        if (url.length) {
          $.getJSON(VuFind.path + url).done(function parseResult(response) {
            _.append(response.data.html);
            _.find('.dynamic-list-item').each(function adjustImages() {
              var img = $(this).find('img');
              if (img.length) {
                img.on('load', function checkImage() {
                  handleImage($(this));
                });
              } else {
                $(this).find('.hidden').removeClass('hidden');
                $(this).find('.dynamic-list-title').addClass('no-image');
              }
            });                
            _.find('.content').slick(settings);
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
