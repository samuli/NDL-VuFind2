finna.advSearch = (function() {

    var initForm = function() {
        $(".main.template-dir-search #advSearchForm").on("submit", function() {
            // Convert data range from/to fields into a "[from TO to]" query
            var field = $(this).find('input[name="daterange[]"]').eq(0).val();
            var fromField = $(this).find('input[name="' + field + 'from"]');
            var toField = $(this).find('input[name="' + field + 'to"]');
            var filter = field + ':"[' + fromField.val() + " TO " + toField.val() + ']"';
            
            $("<input>")
                .attr("type", "hidden")
                .attr("name", "filter[]")
                .attr("value", filter)
                .appendTo($(this));

            // Prevent original fields from getting submitted
            fromField.attr("disabled", "disabled");
            toField.attr("disabled", "disabled");
        });
    };
    
    var my = {
        init: function() {
            initForm();
        }
    };

    return my;

})(finna);
