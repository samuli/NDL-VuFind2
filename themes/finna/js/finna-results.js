finna.results = (function() {

  var initTitleHolds = function (holder) {
      if (typeof holder == "undefined") {
          holder = $(document);
      }
      holder.find('.placehold').unbind('click').on('click', function() {
          var parts = $(this).attr('href').split('?');
          parts = parts[0].split('/');
          var params = deparam($(this).attr('href'));
          params.id = parts[parts.length-2];
          params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
          return Lightbox.get('Record', parts[parts.length-1], params, false, function(html) {
              Lightbox.checkForError(html, Lightbox.changeContent);
          });
      });
  }

  var my = {
    initTitleHolds: initTitleHolds,
    init: function() {
    }
  };

  return my;
})(finna);

