/*global VuFind, finna */
finna.authority = (function finnaAuthority() {
  function initAuthorityRecommendTabs()
  {
    $('div.authority-recommend .nav-tabs li').click(function onTabClick() {
     var self = $(this);
     var id = self.data('id');
     if (self.hasClass('active')) {
       return;
     }
     var parent = self.closest('.authority-recommend');
     var authoritybox = parent.find('.authoritybox');
     parent.find('.nav-tabs li').toggleClass('active', false);
     self.addClass('active');

    var spinner = parent.find('li.spinner');
    spinner.toggleClass('hide', false).show();

     $.getJSON(
       VuFind.path + '/AJAX/JSON',
       {
         method: 'getAuthorityInfo',
         id: id,
         type: 'foo',
         source: 'kavi',
         context: 'recommend',
         searchId: parent.data('search-id')
       }
     )
        .done(function onGetAuthorityInfoDone(response) {
          authoritybox.html(typeof response.data.html !== 'undefined' ? response.data.html : '--');
          var summary = authoritybox.find('.recordSummary');
          finna.layout.initTruncate(authoritybox);


          spinner.hide();
        })
        .fail(function onGetAuthorityInfoFail() {
          authoritybox.text(VuFind.translate('error_occurred'));
          spinner.hide();
        });
    });
  }

  function initInlineInfoLinks()
  {
    $('div.authority').each(function initAuthority() {
      var $authority = $(this);
      $authority.find('a.show-info').click(function onClickShowInfo() {
        var $authorityInfo = $authority.find('.authority-info .content');
        if (!$authority.hasClass('loaded')) {
          $authority.addClass('loaded');
          $.getJSON(
            VuFind.path + '/AJAX/JSON',
            {
              method: 'getAuthorityInfo',
              id: $authority.data('authority'),
              type: $authority.data('type'),
              source: $authority.data('source')
            }
          )
            .done(function onGetAuthorityInfoDone(response) {
              $authorityInfo.html(typeof response.data.html !== 'undefined' ? response.data.html : '--');
            })
            .fail(function onGetAuthorityInfoFail() {
              $authorityInfo.text(VuFind.translate('error_occurred'));
            });
        }
        $authority.addClass('open');
        return false;
      });

      $authority.find('a.hide-info').click(function onClickHideInfo() {
        $authority.removeClass('open');
        return false;
      });
    });
  }

  var my = {
    init: function init() {
      initInlineInfoLinks();
    },
    initAuthorityRecommendTabs: initAuthorityRecommendTabs
  };

  return my;
})();
