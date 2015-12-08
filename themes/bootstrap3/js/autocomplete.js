/**
 * vufind.typeahead.js 0.6
 * ~ @crhallberg
 */
(function ( $ ) {

  $.fn.autocomplete = function(settings) {

    var options = $.extend( {}, $.fn.autocomplete.options, settings );

    function show() {
      $.fn.autocomplete.element.removeClass(options.hidingClass);
    }
    function hide() {
      $.fn.autocomplete.element.addClass(options.hidingClass);
    }

    function populate(item, input, eventType) {
      if (item.hasClass("query")) {
          input.val(item.attr("data-value"));
      }
        
      hide();
      if (typeof options.onselection !== 'undefined') {
        options.onselection(item, input, eventType);
      }
    }

    function createList(data, input) {
      var length = Math.min(options.maxResults, data.length);
      input.data('length', length);
      
      var group = null;
      var groupContainer = null;
        
      var op = $('<div/>');
      for (var i=0, len=Math.min(options.maxResults, data.length); i<len; i++) {
          if (typeof data[i] === 'string') {
              data[i] = {val: data[i]};
          }
          var content = data[i].val;
          if (options.highlight) {
              // escape term for regex
              // https://github.com/sindresorhus/escape-string-regexp/blob/master/index.js
              var escapedTerm = input.val().replace(/[|\\{}()[\]^$+*?.]/g, '\\$&');
              var regex = new RegExp('('+escapedTerm+')', 'ig');
              content = content.replace(regex, '<b>$1</b>');
          }

          var item;
          if (typeof data[i].href === "undefined") {
              item = 
                  //op.append(
                  $('<div/>')
                      .attr('data-value', data[i].val)
                      .html(data[i].val)
                      .addClass('item');
              //);
          } else {
              if (data[i].group != group) {
                  if (groupContainer) {
                      op.append(groupContainer);
                  }
                  group = data[i].group;
                  groupContainer = $('<div/>').addClass("group").addClass("group-" + group);
                  item = groupContainer;
              }
              groupContainer.append(
                  $('<a/>')
                      .attr('href', data[i].href)
                      .attr('data-value', data[i].val)
                      .html(data[i].val)
                      .addClass('item')
                      .addClass(data[i].css.join(" "))
              );
          }
          item.attr('data-index', i+0);
          item.attr('data-value', data[i].val);
          item.attr('data-href', data[i].href);

          item.mouseover(function() {
              $.fn.autocomplete.element.find('.item.selected').removeClass('selected');
              $(this).addClass('selected');
              input.data('selected', this.dataset.index);
          });
          if (typeof data[i].description !== 'undefined') {
              item.append($('<small/>').text(data[i].description));
          }          
      }
        
      op.append(groupContainer);

      $.fn.autocomplete.element.html(op);
        
      $.fn.autocomplete.element.find('.item').mousedown(function() {
          populate($(this), input, {mouse: true});
      });
      align(input, $.fn.autocomplete.element);
    }

    function search(input, element) {
      if (xhr) xhr.abort();
      if (input.val().length >= options.minLength) {
        element.html('<i class="item loading">'+options.loadingString+'</i>');
        show();
        align(input, $.fn.autocomplete.element);
        var term = input.val();
        var cid = input.data('cache-id');
        if (options.cache && typeof $.fn.autocomplete.cache[cid][term] !== "undefined") {
          if ($.fn.autocomplete.cache[cid][term].length === 0) {
            hide();
          } else {
            createList($.fn.autocomplete.cache[cid][term], input, element);
          }
        } else if (typeof options.handler !== "undefined") {
          options.handler(input.val(), function(data) {
            if (options.cache) {
                $.fn.autocomplete.cache[cid][term] = data;
            }
            if (data.length === 0) {
              hide();
            } else {
              createList(data, input, element);
            }
          });
        } else {
          console.error('handler function not provided for autocomplete');
        }
        input.data('selected', -1);
      } else {
        hide();
      }
    }

    function align(input, element) {
      var offset = input[0].getBoundingClientRect();
      element.css({
        position: 'absolute',
        top: offset.top + offset.height,
        left: offset.left,
        maxWidth: offset.width * 2,
        minWidth: offset.width,
        zIndex: 50
      });
    }

    function setup(input, element) {
      if (typeof element === 'undefined') {
        element = $('<div/>')
          .addClass('autocomplete-results hidden')
          .text('<i class="item loading">'+options.loadingString+'</i>');
        align(input, element);
        $('body').append(element);
      }

      input.data('selected', -1);
      input.data('length', 0);

      if (options.cache) {
        var cid = Math.floor(Math.random()*1000);
        input.data('cache-id', cid);
        $.fn.autocomplete.cache[cid] = {};
      }

      input.blur(function(e) {
        if (e.target.acitem) {
          setTimeout(hide, 10);
        } else {
          hide();
        }
      });
      input.click(function() {
        search(input, element);
      });
      input.focus(function() {
        search(input, element);
      });
      input.keyup(function(event) {
        // Ignore navigation keys
        // - Ignore control functions
        if (event.ctrlKey) {
          return;
        }
        // - Function keys (F1 - F15)
        if (112 <= event.which && event.which <= 126) {
          return;
        }
        switch (event.which) {
          case 9:    // tab
          case 13:   // enter
          case 16:   // shift
          case 20:   // caps lock
          case 27:   // esc
          case 33:   // page up
          case 34:   // page down
          case 35:   // end
          case 36:   // home
          case 37:   // arrows
          case 38:
          case 39:
          case 40:
          case 45:   // insert
          case 144:  // num lock
          case 145:  // scroll lock
          case 19: { // pause/break
            return;
          }
          default: {
            search(input, element);
          }
        }
      });
      input.keydown(function(event) {
        var element = $.fn.autocomplete.element;
        var position = $(this).data('selected');
        switch (event.which) {
          // arrow keys through items
          case 38: {
            event.preventDefault();
            element.find('.item.selected').removeClass('selected');
            if (position > 0) {
              position--;
              element.find('.item:eq('+position+')').addClass('selected');
              $(this).data('selected', position);
            } else {
              $(this).data('selected', -1);
            }
            break;
          }
          case 40: {
            event.preventDefault();
            if ($.fn.autocomplete.element.hasClass(options.hidingClass)) {
              search(input, element);
            } else if (position < input.data('length')-1) {
              position++;
              element.find('.item.selected').removeClass('selected');
              element.find('.item:eq('+position+')').addClass('selected');
              $(this).data('selected', position);
            }
            break;
          }
          // enter to nav or populate
          case 9:
          case 13: {
            var selected = element.find('.item.selected');
            if (selected.length > 0) {
              event.preventDefault();
              if (event.which === 13 && selected.attr('href')) {
                location.assign(selected.attr('href'));
              } else {
                populate(selected, $(this), element, {key: true});
                element.find('.item.selected').removeClass('selected');
                $(this).data('selected', -1);
              }
            }
            break;
          }
          // hide on escape
          case 27: {
            hide();
            $(this).data('selected', -1);
            break;
          }
        }
      });

      if (
        typeof options.data    === "undefined" &&
        typeof options.handler === "undefined" &&
        typeof options.preload === "undefined" &&
        typeof options.remote  === "undefined"
      ) {
        return input;
      }

      return element;
    }

    return this.each(function() {

      var input = $(this);

      if (typeof settings === "string") {
        if (settings === "show") {
          show();
          align(input, $.fn.autocomplete.element);
        } else if (settings === "hide") {
          hide();
        } else if (settings === "clear cache" && options.cache) {
          var cid = parseInt(input.data('cache-id'));
          $.fn.autocomplete.cache[cid] = {};
        }
        return input;
      } else {
        if (!$.fn.autocomplete.element) {
          $.fn.autocomplete.element = setup(input);
        } else {
          setup(input, $.fn.autocomplete.element);
        }
      }

      return input;

    });
  };

  var xhr = false;
  var timer = false;
  if (typeof $.fn.autocomplete.cache === 'undefined') {
    $.fn.autocomplete.cache = {};
    $.fn.autocomplete.element = false;
    $.fn.autocomplete.options = {
      ajaxDelay: 200,
      cache: true,
      hidingClass: 'hidden',
      highlight: true,
      loadingString: 'Loading...',
      maxResults: 20,
      minLength: 3
    };
    $.fn.autocomplete.ajax = function(ops) {
      if (timer) clearTimeout(timer);
      if (xhr) xhr.abort();
      timer = setTimeout(
        function() { xhr = $.ajax(ops); },
        $.fn.autocomplete.options.ajaxDelay
      );
    }
  }

}( jQuery ));
