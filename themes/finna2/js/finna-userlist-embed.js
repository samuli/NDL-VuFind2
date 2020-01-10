/*global VuFind, finna, checkSaveStatuses */
finna.userListEmbed = (function userListEmbed() {
  var my = {
    init: function init(id, offset) {
      $('.public-list-embed').not('.inited').each(function initEmbed() {
        var embed = $(this);
        embed.addClass('inited');

        var showMore = embed.find('.show-more');
        var spinner = embed.find('.fa-spinner');
        embed.find('.btn.load-more').click(function initLoadMore() {
          var resultsContainer = embed.find('.search-grid');
          spinner.removeClass('hide').show();

          var btn = $(this);
          btn.addClass('inited');
          
          var id = btn.data('id');
          var offset = btn.data('offset');
          var indexStart = btn.data('start-index');
          var view = btn.data('view');

          btn.hide();
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
              showMore.remove();
              $(response.data.html).find('.result').each(function(ind) {
                resultsContainer.append($(this));
              });
              
              finna.myList.init();
            })
            .fail(function onLoadListFail() {
              btn.show();
              spinner.hide();
            });
          
          return false;
        });
      });
    }
  };

  return my;
})();
