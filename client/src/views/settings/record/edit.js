

Espo.define('views/settings/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        sideView: null,

        layoutName: 'settings',

        buttons: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel',
            }
        ],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:save', function () {
                this.getConfig().set(this.model.toJSON());
            }.bind(this));
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        exit: function (after) {
            if (after == 'cancel') {
                this.getRouter().navigate('#Admin', {trigger: true});
            }
        },
    });
});

