/*global VuFind, finna, checkSaveStatuses */
finna.userListEmbed = (function userListEmbed() {
  var my = {
    init: function init(id, offset) {
      $('.public-list-embed').not('.inited').each(function initEmbed() {
        var embed = $(this);
        embed.addClass('inited');
        embed.find('.load-more').click(function initLoadMore() {
          var resultsContainer = embed.find('.results .result').parent();
          
          console.log("load");
          var btn = $(this);
          var id = btn.data('id');
          var offset = btn.data('offset');
          var indexStart = btn.data('start-index');
          var view = btn.data('view');
          
          $.getJSON(
            VuFind.path + '/AJAX/JSON?method=getUserList',
            {
              id: id,
              offset: offset,
              indexStart: indexStart,
              view: view,
              method: 'getUserList' 
            }
          )
            .done(function onListLoaded(response) {
              btn.remove();

              $(response.data.html).find('.result').each(function(ind) {
                resultsContainer.append($(this));
              });
              
              finna.myList.init();
            })
            .fail(function onLoadListFail() {
            });
          
          return false;
        });
      });
    }
  };

  return my;
})();
