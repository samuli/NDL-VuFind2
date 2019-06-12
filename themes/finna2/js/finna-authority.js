/*global VuFind, finna */
finna.authority = (function finnaAuthority() {
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
    }
  };

  return my;
})();
