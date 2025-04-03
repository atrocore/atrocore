Espo.define('views/record/activities', 'views/record/detail-bottom', function (Dep) {

    return Dep.extend({

        init: function () {
            this.model = this.options.model;
            this.mode = this.options.mode;
            Dep.prototype.init.call(this);
        },

        setup: function () {
            this.canClose = false;

            this.panelList = [{
                model: this.options.model,
                scope: this.options.scope,
                name: "stream",
                label: "",
                title: this.translate('Activities', 'labels'),
                view: "views/stream/panel",
                sticked: false,
                hidden: false,
                expanded: true
            }];
            this.setupPanelViews();
        },

        refresh: function () {
            let stream = this.getView('stream');
            if (stream) {
                stream.actionRefresh();
            }
        }
    });
})