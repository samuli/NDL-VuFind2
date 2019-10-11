/*global VuFind, finna */
finna.authority = (function finnaAuthority() {
  function initAuthorityRecommendTabs()
  {
    $('div.authority-recommend .nav-tabs li').on('click', function(ev) {
     var self = $(this);
     var id = self.data('id');
     var parent = self.closest('.authority-recommend');
     parent.find('.nav-tabs li').toggleClass('active', false);
     self.addClass('active');
     parent.find('.authoritybox').hide();

     var box = parent.find('.authoritybox[data-id="' + id + '"]');
     box.toggleClass('hide', false).show();

     var summary = box.find('.recordSummary');
     if (!summary.hasClass('truncate-field')) {
       summary.addClass('truncate-field');
       finna.layout.initTruncate(box);
     }
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
