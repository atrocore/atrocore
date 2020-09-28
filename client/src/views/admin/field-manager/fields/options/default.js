

Espo.define('views/admin/field-manager/fields/options/default', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setOptionList(this.model.get('options') || ['']);
            this.listenTo(this.model, 'change:options', function () {
                this.setOptionList(this.model.get('options') || ['']);
            }, this);
        }
    });

});
