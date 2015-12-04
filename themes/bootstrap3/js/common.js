/*global btoa, console, hexEncode, isPhoneNumberValid, Lightbox, rc4Encrypt, unescape, VuFind */

function VuFindNamespace(p, s) {
  var path = p;
  var strings = s;

  var getPath = function() { return path; }
  var translate = function(op) { return strings[op]; }

  return {
    getPath: getPath,
    translate: translate
  };
};

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value) {
  if (value) {
    return jQuery('<div />').text(value).html();
  } else {
    return '';
  }
}
function extractClassParams(str) {
  str = $(str).attr('class');
  if (typeof str === "undefined") {
    return [];
  }
  var params = {};
  var classes = str.split(/\s+/);
  for(var i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      var pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }
  return params;
}
// Turn GET string into array
function deparam(url) {
  if(!url.match(/\?|&/)) {
    return [];
  }
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0].replace(/\+/g, ' '));
    if(name.length == 0) {
      continue;
    }
    if(name.substring(name.length-2) == '[]') {
      name = name.substring(0,name.length-2);
      if(!request[name]) {
        request[name] = [];
      }
      request[name].push(decodeURIComponent(pair[1].replace(/\+/g, ' ')));
    } else {
      request[name] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
    }
  }
  return request;
}

// Sidebar
function moreFacets(id) {
  $('.'+id).removeClass('hidden');
  $('#more-'+id).addClass('hidden');
}
function lessFacets(id) {
  $('.'+id).addClass('hidden');
  $('#more-'+id).removeClass('hidden');
}

// Phone number validation
function phoneNumberFormHandler(numID, regionCode) {
  var phoneInput = document.getElementById(numID);
  var number = phoneInput.value;
  var valid = isPhoneNumberValid(number, regionCode);
  if(valid != true) {
    if(typeof valid === 'string') {
      valid = VuFind.translate(valid);
    } else {
      valid = VuFind.translate('libphonenumber_invalid');
    }
    $(phoneInput).siblings('.help-block.with-errors').html(valid);
    $(phoneInput).closest('.form-group').addClass('sms-error');
  } else {
    $(phoneInput).closest('.form-group').removeClass('sms-error');
    $(phoneInput).siblings('.help-block.with-errors').html('');
  }
}

// Lightbox
/*
 * This function adds jQuery events to elements in the lightbox
 *
 * This is a default open action, so it runs every time changeContent
 * is called and the 'shown' lightbox event is triggered
 */
function bulkActionSubmit($form) {
  var button = $form.find('[type="submit"][clicked=true]');
  var submit = button.attr('name');
  var checks = $form.find('input.checkbox-select-item:checked');
  if(checks.length == 0 && submit != 'empty') {
    Lightbox.displayError(VuFind.translate('bulk_noitems_advice'));
    return false;
  }
  if (submit == 'print') {
    //redirect page
    var url = VuFind.getPath() + '/Records/Home?print=true';
    for(var i=0;i<checks.length;i++) {
      url += '&id[]='+checks[i].value;
    }
    document.location.href = url;
  } else {
    $('#modal .modal-title').html(button.attr('title'));
    Lightbox.titleSet = true;
    Lightbox.submit($form, Lightbox.changeContent);
  }
  return false;
}

function registerLightboxEvents() {
  var modal = $("#modal");
  // New list
  $('#make-list').click(function() {
    var get = deparam(this.href);
    get['id'] = 'NEW';
    return Lightbox.get('MyResearch', 'EditList', get);
  });
  // New account link handler
  $('.createAccountLink').click(function() {
    var get = deparam(this.href);
    return Lightbox.get('MyResearch', 'Account', get);
  });
  $('.back-to-login').click(function() {
    Lightbox.getByUrl(Lightbox.openingURL);
    return false;
  });
  // Select all checkboxes
  $(modal).find('.checkbox-select-all').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $(modal).find('.checkbox-select-item').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
  });
  // Highlight which submit button clicked
  $(modal).find("form [type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .fa-spinner').remove();
    // Remove other clicks
    $(modal).find('[type="submit"][clicked=true]').attr('clicked', false);
    // Add useful information
    $(this).attr("clicked", "true");
    // Add prettiness
    if($(modal).find('.has-error,.sms-error').length == 0 && !$(this).hasClass('dropdown-toggle')) {
      $(this).after(' <i class="fa fa-spinner fa-spin"></i> ');
    }
  });
  /**
   * Hide the header in the lightbox content
   * if it matches the title bar of the lightbox
   */
  var header = $('#modal .modal-title').html();
  var contentHeader = $('#modal .modal-body h2');
  contentHeader.each(function(i,op) {
    if (op.innerHTML == header) {
      $(op).hide();
    }
  });
}

function refreshPageForLogin() {
  window.location.reload();
}

function newAccountHandler(html) {
  Lightbox.addCloseAction(refreshPageForLogin);
  var params = deparam(Lightbox.openingURL);
  if (params['subaction'] == 'UserLogin') {
    Lightbox.close();
  } else {
    Lightbox.getByUrl(Lightbox.openingURL);
    Lightbox.openingURL = false;
  }
  return valid == true;
}

// This is a full handler for the login form
function ajaxLogin(form) {
  Lightbox.ajax({
    url: VuFind.getPath() + '/AJAX/JSON?method=getSalt',
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        var salt = response.data;

        // extract form values
        var params = {};
        for (var i = 0; i < form.length; i++) {
          // special handling for password
          if (form.elements[i].name == 'password') {
            // base-64 encode the password (to allow support for Unicode)
            // and then encrypt the password with the salt
            var password = rc4Encrypt(
                salt, btoa(unescape(encodeURIComponent(form.elements[i].value)))
            );
            // hex encode the encrypted password
            params[form.elements[i].name] = hexEncode(password);
          } else {
            params[form.elements[i].name] = form.elements[i].value;
          }
        }

        // login via ajax
        Lightbox.ajax({
          type: 'POST',
          url: VuFind.getPath() + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
          success: function(response) {
            if (response.status == 'OK') {
              Lightbox.addCloseAction(refreshPageForLogin);
              // and we update the modal
              var params = deparam(Lightbox.lastURL);
              if (params['subaction'] == 'UserLogin') {
                Lightbox.close();
              } else {
                Lightbox.getByUrl(
                  Lightbox.lastURL,
                  Lightbox.lastPOST,
                  Lightbox.changeContent
                );
              }
            } else {
              Lightbox.displayError(response.data);
            }
          }
        });
      } else {
        Lightbox.displayError(response.data);
      }
    }
  });
}

// Ready functions
function setupOffcanvas() {
  if($('.sidebar').length > 0) {
    $('[data-toggle="offcanvas"]').click(function () {
      $('body.offcanvas').toggleClass('active');
      var active = $('body.offcanvas').hasClass('active');
      var right = $('body.offcanvas').hasClass('offcanvas-right');
      if((active && !right) || (!active && right)) {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-right').addClass('fa-chevron-left');
      } else {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-left').addClass('fa-chevron-right');
      }
    });
    $('[data-toggle="offcanvas"]').click().click();
  } else {
    $('[data-toggle="offcanvas"]').addClass('hidden');
  }
}

function setupBacklinks() {
  // Highlight previous links, grey out following
  $('.backlink')
    .mouseover(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'underline'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':'#999'});
        t = t.next();
      } while(t.length > 0);
    })
    .mouseout(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'none'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':''});
        t = t.next();
      } while(t.length > 0);
    });
}

function updateQueryStringParameter(uri, key, value) {
    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
    var separator = uri.indexOf('?') !== -1 ? "&" : "?";
    if (uri.match(re)) {
        return uri.replace(re, '$1' + key + "=" + value + '$2');
    }
    else {
        return uri + separator + key + "=" + value;
    }
}

function setupAutocomplete() {
  // Search autocomplete
  $('.autocomplete').each(function(i, op) {
    $(op).autocomplete({
      maxResults: 20,
      loadingString: VuFind.translate('loading')+'...',
      handler: function(query, cb) {
        var preserveFilters = $(".searchFormKeepFilters").is(":checked");
        var fields = {AllFields: "title", author: "authors"};
        var searcher = extractClassParams(op);
        var searchType = searcher['type'] ? searcher['type'] : $(op).closest('.searchForm').find('.searchForm_type').val();
        $.fn.autocomplete.ajax({
          url: VuFind.getPath() + '/SearchAPI/Search',
          data: {
            lookfor:query,
            method:'getACSuggestions',
            searcher:searcher['searcher'],
            type: searchType,
            field: ["title", "authors", "subjects"],
            limit: 5,
            facet: preserveFilters ? [] : ["online_boolean", "format"],
            filter: preserveFilters ? $.deparam(document.location.href).filter : []
          },
          dataType:'json',
          success: function(json) {
            if (json.status == 'OK' && json.resultCount > 0) {
              var datums = [];
              var parser = document.createElement('a');
              var location = decodeURI(document.location.href);
              if (!preserveFilters) {
                  location = location.replace(/filter\[\]=.*(\&)?/g, "");
              } else {
                  location = updateQueryStringParameter(location, "dfApplied", "1");                               
              }
              
              parser.href = location;
              href = parser.search;

              var base = VuFind.getPath() + "/Search/Results";
              var suggestionTitles = [];
              
              suggestions = $(json.records).map(function(ind, obj) {
                  field = null;
                  switch (searchType) {
                  case "Author":
                      if (!("authors" in obj)) {
                          return null;
                          break;
                      }
                      if ("main" in obj.authors && obj.authors.main != "") {
                          field = obj.authors.main;
                      } else if ("secondary" in obj.authors) {
                          field = obj.authors.secondary[0];
                      }
                      break;
                  
                  case "Subject":
                      if (!("subjects" in obj)) { 
                          return null;
                          break;
                      }
                      if (!obj.subjects.length) {
                          return null;
                          break;
                      }
                      field = obj.subjects[0];
                      
                      break;
                      
                  default:
                      field = obj.title;
                      break;
                  }
                  if ($.inArray(field, suggestionTitles) !== -1) {
                      return null;
                  }

                  suggestionTitles.push(field);
                  suggestionHref = base + updateQueryStringParameter(href, "lookfor", field);
                  suggestionHref = updateQueryStringParameter(suggestionHref, "type", searchType);
                  
                  return  {
                      val: field,
                      href: suggestionHref,
                      css: ["query"],
                      group: "suggestions"
                  };
              });
              datums = datums.concat(suggestions.toArray());

              var lookfor = decodeURI($(".searchForm_lookfor").val());

              // Pictures
              picHref = base + "?lookfor=" + lookfor;
              picHref += '&filter[]=online_boolean:"1"&filter[]=~format:"0/Image/"&filter[]=~format:"0/PhysicalObject/"&filter[]=~format:"0/WorkOfArt/"&filter[]=~format:"0/Map/"&filter[]=~format:"0/Place/"&filter[]=~format:"1/Other/Letter/"&filter[]=~format:"1/Other/Print/"';
              picHref += '&type=AllFields&limit=50&view=grid';
              datums.push({
                  val: "",
                  href: picHref,
                  css: ["query query-pictures"],
                  group: "facets"
              });
              
              // Facets
              var facets = [];
              if ("facets" in json) {                  
                  $.each(json.facets, function(facet, items) {
                      $.each(items.slice(0,3), function(ind, obj) {
                          if (obj.count == 0) {
                              return false;
                          }
                          facetHref = decodeURI(obj.href);
                          facetHref = facetHref.substr(1).replace(/%3A/g, ':').replace(/%2F/g, '/').replace(/&amp;/g, '&');
                          facets.push({
                              val: obj.displayText + ' (' + obj.count + ')',
                              href: base + "?" + facetHref,
                              css: ["facet", "facet-" + facet, "facet-" + facet + "-" + obj.value],
                              group: "facets"                              
                          });
                      });                      
                  });
              }
              datums = datums.concat(facets);

              // Exact
              var exact = lookfor;
              var exactHref = parser.search;
              if (lookfor.substr(0,1) !== '"' && lookfor.substr(-1) !== '"') {
                  exact = '"' + lookfor + '"';
              }                
              datums.push({
                  val: exact,
                  href: base + updateQueryStringParameter(exactHref, "lookfor", exact),
                  css: ["query query-exact"],
                  group: "operators"
              });

              // Search types
              var typeHref = parser.search;
              $.each(["Title", "Author", "Subject"], function(ind, type) {
                  typeHref = updateQueryStringParameter(typeHref, "type", type);
                  typeHref = updateQueryStringParameter(typeHref, "lookfor", lookfor);                  
                  datums.push({
                      val: lookfor,
                      href: base + typeHref,
                      css: ["query query-" + type],
                      group: "operators"
                  });
              });

              cb(datums);
            } else {
              cb([]);
            }
          }
        });
      }
    });
  });
  // Update autocomplete on type change
  $('.searchForm_type').change(function() {
    var $lookfor = $(this).closest('.searchForm').find('.searchForm_lookfor[name]');
    $lookfor.focus();
  });
}

/**
 * Handle arrow keys to jump to next record
 * @returns {undefined}
 */
function keyboardShortcuts() {
    var $searchform = $('#searchForm_lookfor');
    if ($('.pager').length > 0) {
        $(window).keydown(function(e) {
          if (!$searchform.is(':focus')) {
            $target = null;
            switch (e.keyCode) {
              case 37: // left arrow key
                $target = $('.pager').find('a.previous');
                if ($target.length > 0) {
                    $target[0].click();
                    return;
                }
                break;
              case 38: // up arrow key
                if (e.ctrlKey) {
                    $target = $('.pager').find('a.backtosearch');
                    if ($target.length > 0) {
                        $target[0].click();
                        return;
                    }
                }
                break;
              case 39: //right arrow key
                $target = $('.pager').find('a.next');
                if ($target.length > 0) {
                    $target[0].click();
                    return;
                }
                break;
              case 40: // down arrow key
                break;
            }
          }
        });
    }
}

$(document).ready(function() {
  // Setup search autocomplete
  setupAutocomplete();
  // Setup highlighting of backlinks
  setupBacklinks() ;
  // Off canvas
  setupOffcanvas();
  // Keyboard shortcuts in detail view
  keyboardShortcuts();

  // support "jump menu" dropdown boxes
  $('select.jumpMenu').change(function(){ $(this).parent('form').submit(); });

  // Checkbox select all
  $('.checkbox-select-all').change(function() {
    $(this).closest('form').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $('.checkbox-select-item').change(function() {
    $(this).closest('form').find('.checkbox-select-all').prop('checked', false);
  });

  // handle QR code links
  $('a.qrcodeLink').click(function() {
    if ($(this).hasClass("active")) {
      $(this).html(VuFind.translate('qrcode_show')).removeClass("active");
    } else {
      $(this).html(VuFind.translate('qrcode_hide')).addClass("active");
    }

    var holder = $(this).next('.qrcode');
    if (holder.find('img').length == 0) {
      // We need to insert the QRCode image
      var template = holder.find('.qrCodeImgTag').html();
      holder.html(template);
    }
    holder.toggleClass('hidden');
    return false;
  });

  // Print
  var url = window.location.href;
  if(url.indexOf('?' + 'print' + '=') != -1  || url.indexOf('&' + 'print' + '=') != -1) {
    $("link[media='print']").attr("media", "all");
    $(document).ajaxStop(function() {
      window.print();
    });
    // Make an ajax call to ensure that ajaxStop is triggered
    $.getJSON(VuFind.getPath() + '/AJAX/JSON', {method: 'keepAlive'});
  }

  // Advanced facets
  $('.facetOR').click(function() {
    $(this).closest('.collapse').html('<div class="list-group-item">'+VuFind.translate('loading')+'...</div>');
    window.location.assign($(this).attr('href'));
  });

  $('[name=bulkActionForm]').submit(function() {
    return bulkActionSubmit($(this));
  });
  $('[name=bulkActionForm]').find("[type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .fa-spinner').remove();
    // Remove other clicks
    $(this).closest('form').find('[type="submit"][clicked=true]').attr('clicked', false);
    // Add useful information
    $(this).attr("clicked", "true");
  });
});
