

Espo.define('views/admin/user-interface', 'views/settings/record/edit', function (Dep) {

    return Dep.extend({

        layoutName: 'userInterface',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.controlColorsField();
            this.listenTo(this.model, 'change:scopeColorsDisabled', this.controlColorsField, this);
        },

        controlColorsField: function () {
            if (this.model.get('scopeColorsDisabled')) {
                this.hideField('tabColorsDisabled');
            } else {
                this.showField('tabColorsDisabled');
            }
        }

    });
});
