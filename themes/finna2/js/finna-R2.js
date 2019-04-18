/*global finna, VuFind */
finna.R2 = (function finnaR2() {

  function initModal() {
    // Transform form h1-element to a h2 so that the modal gets a proper title bar
    var modal = $('#modal');
    modal.on('show.bs.modal', function onShowModal (/*e*/) {
      var title = $(this).find('.feedback-content h1:first-child');
      if (title.length > 0) {
        var body = $(this).find('.modal-body');
        var h2 = $('<h2/>').text(title.text());
        h2.prependTo(body);
        title.remove();
      }
    });
  }

  function initAutoOpen() {
    $('.R2-status .register .btn-primary').trigger('click');
  }
  
  function initCheckPermission() {
    initModal();
    $('div.check-permission').not('.inited').each(function addCheckPermission() {
      var id = $(this).data('id');

      var url = VuFind.path + '/AJAX/JSON?method=getRemsPermission';
      $.ajax({
        type: 'GET',
        url: url,
        data: { recordId: id},
        dataType: 'json'
      })
        .done(function onCheckPermissionDone(result) {
          var status = result.data;
          if (status !== null) {            
            $('.R2-status').toggleClass('hide', true);
            $('.R2-status-' + status).removeClass('hide');
          }
        })
        .fail(function onCheckPermissionFail() {
          $('.R2-status.status-error').removeClass('hide');
        });
    });
  }
  
  var my = {
    initAutoOpen: initAutoOpen,
    initCheckPermission: initCheckPermission,
    initModal: initModal
  };

  return my;
})();
