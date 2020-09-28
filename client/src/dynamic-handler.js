

define('dynamic-handler', [], function () {

    var DynamicHandler = function (recordView) {
        this.recordView = recordView;
        this.model = recordView.model;
    }

    _.extend(DynamicHandler.prototype, {

        init: function () {},

        onChange: function (model, o) {},

        getMetadata: function () {
            return this.recordView.getMetadata()
        }
    });

    DynamicHandler.extend = Backbone.Router.extend;

    return DynamicHandler;
});
