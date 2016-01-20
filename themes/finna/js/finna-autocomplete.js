/*global VuFind*/
finna.autocomplete = (function() {
    menuItems: [];
    menuItemCache = false;

    var parseResponse = function (data, handlers) {
        menuItems = [];
        var datums = [];
        var suggestions = $.map(data[0], function(obj, i) {
            menuItems.push({label: obj, type: 'suggestion'});
            return obj;
        });
        $.merge(datums, suggestions);
        
        var facets = [];
        $.each(data[1], function (facet, data) {
            var items = $.map(data['values'], function (item, i) {
                var label = item[0] + ' (' + item[1] + ')';
                var href = decodeURIComponent(item[2]).replace(/&amp;/g, '&');
                menuItems.push({
                    label: label,
                    type: 'facet facet-' + facet,
                    href: href,
                    facet: facet
                });
                return {val: label, href: href};
            });
            facets = $.merge(facets, items);
        });
        $.merge(datums, facets);

        var query = $('.searchForm_lookfor').val();
        var handlers = handlers.split(';').map(function (obj, i) {
          var handler = obj.split('|');
          var label = handler[1] + ": " + query;
          menuItems.push({label: label, type: 'handler handler-' + handler[0]});
          return {val: label, href: obj.href};
        });
        $.merge(datums, handlers);

        console.log("handlers: %o", handlers);
                    
        
        return datums;
        /*
          var searchTypes = $.map(data[2], function (obj, i) {
          var label = obj.label + ": " + query;
          menuItems.push({label: label, href: obj.href, type: 'handler'});
          return {val: label, href: obj.href};
          });
          $.merge(datums, searchTypes);
        */
        $('.autocomplete-results').bind('DOMSubtreeModified', function() { autocompleteUpdated(); });
    };

    var autocompleteUpdated = function () {
        var menu = $('.autocomplete-results');
        var content = getAutocompleteContent(menu);
        if (menuItemCache == content) {
            return;
        }

        menu.unbind('DOMSubtreeModified');

        var container = $('<div/>');
        menu.find('.item').each(function(ind, obj) {
            var data = menuItems[ind];
            if (typeof data == 'undefined') {
                return false;
            }
            var item = $('<div/>').html($(obj).html());
            $.each($(obj).prop('attributes'), function() {
                item.attr(this.name, this.value);
            });
            item.addClass(data['type']);
            $(obj).unbind('mousedown').on('mousedown', function(e) {
                location.href = data['href'];
                return false;
            });
            $(obj).replaceWith(item.unwrap()[0]);
        });

        menuItemCache = getAutocompleteContent(menu);
        menu.bind('DOMSubtreeModified', function() { autocompleteUpdated(); });
    };

    var getAutocompleteContent = function (menu) {
        return menu.find('.item').map(function(ind, obj) {
            return $(this).html();
        }).toArray().join('');
    };
    var setupAutocomplete = function () {
        // Search autocomplete
        $('.autocomplete-finna').each(function(i, op) {
            $(op).autocomplete({
                maxResults: 100,
                loadingString: VuFind.translate('loading')+'...',
                handler: function(query, cb) {
                    var searcher = extractClassParams(op);
                    var hiddenFilters = [];
                    $(op).closest('.searchForm').find('input[name="hiddenFilters[]"]').each(function() {
                        hiddenFilters.push($(this).val());
                    });
                    $.fn.autocomplete.ajax({
                        url: VuFind.getPath() + '/AJAX/JSON',
                        data: {
                            q:query,
                            method:'getACSuggestions',
                            searcher:searcher['searcher'],
                            type:searcher['type'] ? searcher['type'] : $(op).closest('.searchForm').find('.searchForm_type').val(),
                            hiddenFilters:hiddenFilters 
                        },
                        dataType:'json',
                        success: function(json) {
                            if (json.status == 'OK' && json.data.length > 0) {
                                $('.autocomplete-results').bind('DOMSubtreeModified', function() { autocompleteUpdated(); });
                                cb(parseResponse(json.data, searcher['handlers']));
                            } else {
                                cb([]);
                            }
                        }
                    });
                }
            });
        });
    };

    var my = {
        init: function() {
            console.log("finna ac init");
            setupAutocomplete();
        }
    };

    return my;
})(finna);


