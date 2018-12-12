

/*global VuFind, finna, createUV */

finna.UV = (function finnaUV() {

  var wrapper;
  var el;
  var uv;
  var dataProvider;
  
  function close() {
    el.empty();
    wrapper.hide();
  }
  
  function reposition() {
    wrapper.offset({top: $(document).scrollTop()});
  }
  
  function resize() {
    var uvEl = el.find('>div');
    uvEl.width($(window).width());
    uvEl.height($(window).height()-el.position().top);
    if (uv) {
      uv.resize();
    }
  }

  function getData(id) {
    id = encodeURIComponent(id).replace(/[!'()]/g, escape).replace(/\*/g, "%2A");

    var data = {
      root: '../themes/finna2/js/vendor/uv-build',
      configUri: VuFind.path + '/themes/finna2/js/vendor/uv-build/uv-config.json',
      iiifResourceUri: VuFind.path + '/AJAX/JSON?method=GetIIIFManifest&id=' + id,
      canvasIndex: 0,
      isReload: false
    };

    return data;
  }

  function initPagination(id) {
    var prevId = null;
    var nextId = null;
    var prevBtn = wrapper.find('.paginate.prev');
    var nextBtn = wrapper.find('.paginate.next');

    
    prevBtn.hide();
    nextBtn.hide();
    
    var recEl = $(".recordcover-container[data-id=\"" + id + "\"] .image-popup-trigger").closest('div.result');
    if (recEl.length) {
      var prev = recEl.prev('div.result');
      if (prev.length) {
        prevId = prev.find('.hiddenId').val();
        prevBtn.show().off('click').on('click', function() { show(prevId); });
      }
      var next = recEl.next('div.result');
      if (next.length) {
        nextId = next.find('.hiddenId').val();
        nextBtn.show().off('click').on('click', function() { show(nextId); });
      }
    }
  }
  
  function show(id) {
    uv.set(getData(id));
    initPagination(id);
  }
  
  function open(id) {
    wrapper = $('.uv-wrapper');
    wrapper.show();
    wrapper.find('.close').on('click', function() {
      close();
    });

    initPagination(id);

    el = wrapper.find('#uv');

    window.requestAnimationFrame(function() {
      dataProvider = new UV.URLDataProvider();
      var data = getData(id);
      uv = createUV(el, data, dataProvider);

      window.onresize = function() {
        resize();
      }

      resize();
      reposition();
    });
  }

  // if the embed script has been included in the page for testing, don't append it.
  var scriptIncluded = $('#embedUV').length;

  var my = {
    uv: uv,
    dataProvider: dataProvider,
    open: open,
    show: show
  };
  
  return my;
})();
