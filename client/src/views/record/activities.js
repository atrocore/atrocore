Espo.define('views/record/activities', 'views/record/detail-bottom', function (Dep) {

    return Dep.extend({

        init: function () {
            this.model = this.options.model;
            Dep.prototype.init.call(this);
        },

        setup: function () {
            this.canClose = false;

            this.panelList = [{
                model: this.options.model,
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