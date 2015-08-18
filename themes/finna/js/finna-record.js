finna.record = (function() {
    
    var initDescription = function() {
        var description = $("#description_text");
        if (description.length) {
            var id = description.data('id');
            var url = path + '/AJAX/JSON?method=getDescription&id=' + id;
            $.getJSON(url, function(response) {
                if (response.status === 'OK' && response.data.length > 0) {
                    description.html(response.data);
                    description.wrapInner('<div class="truncate-field wide"><p class="summary"></p></div>');
                    finna.layout.initTruncate(description);
                } else {
                    description.hide();
                }
            });
        }
    }

    getRequestLinkData = function(element, recordId) {
      var vars = {}, hash;
      var hashes = element.href.slice(element.href.indexOf('?') + 1).split('&');
    
      for (var i = 0; i < hashes.length; i++) {
        hash = hashes[i].split('=');
        var x = hash[0];
        var y = hash[1];
        vars[x] = y;
      }
      vars['id'] = recordId;
      return vars;
    }
    
    checkRequestsAreValid = function(elements, requestType, blockedClass) {
      if (!elements[0]) {
        return;
      }
      var recordId = elements[0].href.match(/\/Record\/([^\/]+)\//)[1];
      
      var vars = [];
      $.each(elements, function(idx, element) {
        vars.push(getRequestLinkData(element, recordId));  
      });
      
    
      var url = path + '/AJAX/JSON?method=checkRequestsAreValid';
      $.ajax({
        dataType: 'json',
        data: {id: recordId, requestType: requestType, data: vars},
        method: 'POST',
        cache: false,
        url: url,
        success: function(responses) {
          if (responses.status == 'OK') {
            $.each(responses.data, function(idx, response) {
              var element = elements[idx];
              if (response.status) {
                $(element).removeClass('disabled')
                  .attr('title', response.msg)
                  .html(response.msg);
                } else {
                  $(element).remove();
                }
            });
          } else if (responses.status == 'NEED_AUTH') {
            $(element).replaceWith('<span class="' + blockedClass + '">' + responses[0].msg + '</span>');
          }
        }
      });
    }
    
    var setUpCheckRequest = function() {
      checkRequestsAreValid($('.expandedCheckRequest').removeClass('expandedCheckRequest'), 'Hold', 'holdBlocked');
      checkRequestsAreValid($('.expandedCheckStorageRetrievalRequest').removeClass('expandedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest', 'StorageRetrievalRequestBlocked');
      checkRequestsAreValid($('.expandedCheckILLRequest').removeClass('expandedCheckILLRequest'), 'ILLRequest', 'ILLRequestBlocked');
    }
    
    var initHoldingsControls = function() {
        $('.holdings-container-heading').click(function () {
            $(this).nextUntil('.holdings-container-heading').toggleClass('collapsed');
            if ($('.location .fa', this).hasClass('fa-arrow-down')) {
                $('.location .fa', this).removeClass('fa-arrow-down');
                $('.location .fa', this).addClass('fa-arrow-right'); 
            }
            else {
                $('.location .fa', this).removeClass('fa-arrow-right');
                $('.location .fa', this).addClass('fa-arrow-down');
                var rows = $(this).nextUntil('.holdings-container-heading');
                checkRequestsAreValid(rows.find('.collapsedCheckRequest').removeClass('collapsedCheckRequest'), 'Hold', 'holdBlocked');
                checkRequestsAreValid(rows.find('.collapsedCheckStorageRetrievalRequest').removeClass('collapsedCheckStorageRetrievalRequest'), 'StorageRetrievalRequest', 'StorageRetrievalRequestBlocked');
                checkRequestsAreValid(rows.find('.collapsedCheckILLRequest').removeClass('collapsedCheckILLRequest'), 'ILLRequest', 'ILLRequestBlocked');
            }
        });
    };

    var initRecordFeedbackForm = function() {
        var id = $('.hiddenId')[0].value;
        $('#feedback-record').click(function() {
          var params = extractClassParams(this);
          return Lightbox.get(params.controller, 'Feedback', {id:id});
        });

        Lightbox.addFormCallback('feedbackRecord', function(html) {
            Lightbox.confirm(vufindString['feedback_success']);
        });
    };

    var initMobileModals = function() {
      var id = $('.hiddenId')[0].value;
      $('#cite-record-mobile').click(function() {
        var params = extractClassParams(this);
        return Lightbox.get(params['controller'], 'Cite', {id:id});
      });
      // Mail lightbox
      $('#mail-record-mobile').click(function() {
        var params = extractClassParams(this);
        return Lightbox.get(params['controller'], 'Email', {id:id});
      });
      // Save lightbox
      $('#save-record-mobile').click(function() {
        var params = extractClassParams(this);
        return Lightbox.get(params['controller'], 'Save', {id:id});
      });
    };
    
    var initSaveRecordLinks = function(holder) {
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }
        holder.find('.save-record').one("click", function() {
            var parts = this.href.split('/');
            return finna.layout.lightbox.get(parts[parts.length-3],'Save',{id:$(this).attr('id')});
        });
    };

    var checkSaveStatuses = function(holder) {
        // Global checkSaveStatus is overridden by this method in finna.js
        if (typeof(holder) == "undefined") {
            holder = $("body");
        }

        var data = $.map(holder.find('.result,.record'), function(i) {
            if($(i).find('.hiddenId').length == 0 || $(i).find('.hiddenSource').length == 0) {
                return false;
            }
            return {'id':$(i).find('.hiddenId').val(), 'source':$(i).find('.hiddenSource')[0].value};
        });

        if (data.length) {
            var ids = [];
            var srcs = [];
            for (var i = 0; i < data.length; i++) {
                ids[i] = data[i].id;
                srcs[i] = data[i].source;
            }
            $.ajax({
                dataType: 'json',
                url: path + '/AJAX/JSON?method=getSaveStatuses',
                data: {id:ids, 'source':srcs},
                success: function(response) {
                    if(response.status == 'OK') {
                        holder.find('.savedLists > ul').empty();
                        $.each(response.data, function(i, result) {
                            var $container = holder.find('input[value="' + result.record_id + '"]').closest(".result");
                            if ($container.length) {
                                $container = $container.find(".savedLists");
                            }
                            if ($container.length == 0) { // Record view
                                $container = $('#savedLists');
                            }
                            var $ul = $container.children('ul:first');
                            if ($ul.length == 0) {
                                $container.append('<ul></ul>');
                                $ul = $container.children('ul:first');
                            }
                            var html = '<li><a href="' + path + '/MyResearch/MyList/' + result.list_id + '">'
                                + result.list_title + '</a></li>';
                            $ul.append(html);
                            $container.removeClass('hidden');
                        });
                    }
                }
            });
            initSaveRecordLinks(holder);
        }
    };

    var my = {
        checkRequestsAreValid: checkRequestsAreValid,
        checkSaveStatuses: checkSaveStatuses,
        initSaveRecordLinks: initSaveRecordLinks,
        init: function() {
            initDescription();
            initHoldingsControls();
            initRecordFeedbackForm();
            setUpCheckRequest();
            initMobileModals();
        },
    };

    return my;
})(finna);
