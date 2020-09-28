

Espo.define('ajax', [], function () {

    var Ajax = Espo.Ajax = {

        request: function (url, type, data, options) {
            var options = options || {};
            options.type = type;
            options.url = url;

            if (data) {
                options.data = data;
            }

            var xhr = $.ajax(options);

            return xhr;

            var obj = {
                then: xhr.then,
                fail: xhr.fail,
                catch: xhr.fail
            };

            return obj;
        },


        postRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }
            return Ajax.request(url, 'POST', data, options);
        },

        patchRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }
            return Ajax.request(url, 'PATCH', data, options);
        },

        putRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }
            return Ajax.request(url, 'PUT', data, options);
        },

        getRequest: function (url, data, options) {
            return Ajax.request(url, 'GET', data, options);
        }
    };

    return Ajax;

});
