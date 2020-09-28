
Espo.define('views/email-filter/fields/action', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.controlActionOptions();
            this.listenTo(this.model, 'change:parentType', this.controlActionOptions, this);
        },

        controlActionOptions: function () {
            if (this.model.get('parentType') === 'User') {
                this.params.options = ['Skip', 'Move to Folder'];
            } else {
                this.params.options = ['Skip'];
            }
            if (this.isRendered()) {
                this.reRender();
            }
        }

    });

});
