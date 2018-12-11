

/*global VuFind, finna */

finna.UV = (function finnaUV() {

  var wrapper;
  var el;
  
  function close() {
    el.empty();
    wrapper.hide();
  }
  
  function reposition() {
    wrapper.offset({top: $(document).scrollTop()});
  }
  
  function resize() {
    el.width($(window).width());
    el.height($(window).height()-el.position().top);
  }
  
  function open(id) {
    wrapper = $('.uv-wrapper');
    wrapper.show();
    wrapper.find('.close').on('click', function() {
      close();
    });
    
    el = wrapper.find('#uv');

    resize();
    reposition();

    id = encodeURIComponent(id).replace(/%20/g, "%2B");
    window.requestAnimationFrame(function() {
      createUV(el, {
        //root: VuFind.path + '/themes/finna2/js/vendor/uv-3.0.22',
        //configUri: VuFind.path + '/themes/finna2/js/vendor/uv-3.0.22/uv-config.json',

        
        root: '../themes/finna2/js/vendor/uv',
        configUri: VuFind.path + '/themes/finna2/js/vendor/uv/uv-config.json',

        iiifResourceUri: VuFind.path + '/AJAX/JSON?method=GetIIIFManifest&id=' + id
        
      }, new UV.URLDataProvider());
    });
    
  }

  // if the embed script has been included in the page for testing, don't append it.
  var scriptIncluded = $('#embedUV').length;

  var my = {
    open: open
  };
  
  return my;
})();
