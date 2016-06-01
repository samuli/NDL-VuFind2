/*global VuFind*/
finna = $.extend(finna, {
organisationInfo: function() {
//finna.organisationInfo = (function() {
    var organisationList = {};
    var currentWeekNum = null;
    var currentScheduleInfo = null;
    var loading = false;

    var query = function(parentId, queryParams, callback) {
        var url = VuFind.path + '/AJAX/JSON';
        var params = {method: 'getOrganisationInfo', parent: parentId, params: queryParams};

        $.getJSON(url, params)
        .done(function(response) {
            loading = false;
            if (response.data) {
                callback(true, response.data);
                return;
            }
            callback(false, 'Error reading organisation info');
        })
        .fail(function(response, textStatus, err) {
            loading = false;
            var err = false;
            if (typeof response.responseJSON != 'undefined') {
                err = response.responseJSON.data;
            }
            callback(false, err);
        });
    };

    var getOrganisations = function(target, parent, callback) {
        if (typeof parent == 'undefined') {
            return;
        }

        if (parent in organisationList) {
            callback(organisationList[parent]);
        }

        var me = self;
        var response = query(parent, {action: 'list', target: target}, function(success, response) {
            if (!success) {
                callback(false);
                return;
            }
            var id = getField(response, 'id');
            var list = getField(response, 'list');

            $.each(list, function(ind, obj) {
                organisationList[obj.id] = obj;
            });

            console.log("org: %o", organisationList);
            callback(response);
        });
    };

    var getInfo = function(id) {
        if (!(id in organisationList)) {
            return false;
        }
        return organisationList[id];
    };

    var getDetails = function(id) {
        if (!(id in organisationList)) {
            return false;
        }

        var data = organisationList[id];
        var details = {};

        openNow = getField(data, 'openNow');
        if (openNow !== null) {
            details['openNow'] = openNow;
        }

        $(['name', 'email', 'homepage', 'routeUrl', 'mapUrl', 'openToday', 
           'buildingYear', 'openTimes', 'schedule-descriptions']
         ).each(function(ind, field) {
            if (val = getField(data, field)) {
                details[field] = val;
            }
        });
        
        if (mapUrl = getField(data, 'mapUrl')) {
            var address = '';
            if (street = getField(data.address, 'street')) {
                address += street;
            }
            if (zipcode = getField(data.address, 'zipcode')) {
                address += ', ' + zipcode;
            }
            if (city = getField(data.address, 'city')) {
                address += ' ' + city;
            }

            details['address'] = address;
        }

        if (cached = getCachedDetails(id)) {
            details = $.extend(details, {details: cached});
        }
        return details;
    };

    var getSchedules = function(target, parent, id, periodStart, dir, fullDetails, allServices, callback) {
        if (fullDetails) {
            details = getCachedDetails(id);
            if (details && details.periodStart) {
                if (details.periodStart == periodStart) {
                    callback(details);
                    return;
                }
            }
        }

        var params = {
            target: target, action: 'details', id: id, 
            fullDetails: fullDetails ? 1 : 0, 
            allServices: allServices ? 1 : 0
        };

        if (periodStart) {
            params = $.extend(params, {periodStart: periodStart});
        }
        if (dir) {
            params = $.extend(params, {dir: dir});
        }

        query(parent, params, function(success, obj) {
            if (!success) {
                callback(false);
                return;
            }
            if (fullDetails) {
                cacheDetails(id, obj);
            }
            var result = {};
            $(['openTimes', 'periodStart', 'weekNum', 'currentWeek', 'phone', 
               'links', 'facility-image', 'services', 'pictures', 'rss']
             ).each(function(ind, field) {
                    if (val = getField(obj, field, id)) {
                        result[field] = val;
                    }
                });
            
            callback(result);
        });
    };

    var getField = function(obj, field, organisationId) {
        if (field in obj && typeof obj[field] != 'undefined') {
            return obj[field];
        }
        if (organisationId) {
            if (cache = getCachedDetails(organisationId)) {
                if (typeof cache[field] != 'undefined') {
                    return cache[field];
                }
            }
        }
        return null;
    };

    var getCachedDetails = function(id) {
        if (typeof organisationList[id]['details'] != 'undefined') {
            return organisationList[id]['details'];
        }
        return null;
    };

    var cacheDetails = function(id, details) {
        organisationList[id]['details'] = details;
    };

    var my = {
        getOrganisations: getOrganisations,
        getInfo: getInfo,
        getDetails: getDetails,
        getSchedules: getSchedules
    };
    return my;
}
});
//)(finna);
