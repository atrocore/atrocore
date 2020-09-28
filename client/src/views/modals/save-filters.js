


Espo.define('views/modals/save-filters', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        cssName: 'save-filters',

        template: 'modals/save-filters',

        data: function () {
            return {
                dashletList: this.dashletList,
            };
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.header = this.translate('Save Filters');

            var model = new Model();
            this.createView('name', 'views/fields/varchar', {
                el: this.options.el + ' .field[data-name="name"]',
                defs: {
                    name: 'name',
                    params: {
                        required: true
                    }
                },
                mode: 'edit',
                model: model
            });
        },

        actionSave: function () {
            var nameView = this.getView('name');
            nameView.fetchToModel();
            if (nameView.validate()) {
                return;
            }
            this.trigger('save', nameView.model.get('name'));
            return true;
        },
    });
});


