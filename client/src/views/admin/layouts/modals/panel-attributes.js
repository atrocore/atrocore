


Espo.define('views/admin/layouts/modals/panel-attributes', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        _template: '<div class="edit-container">{{{edit}}}</div>',

        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    text: this.translate('Apply'),
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    text: 'Cancel'
                }
            ];

            var model = new Model();
            model.name = 'LayoutManager';
            model.set(this.options.attributes || {});

            var attributeList = this.options.attributeList;

            var attributeDefs = this.options.attributeDefs;

            this.createView('edit', 'views/admin/layouts/record/edit-attributes', {
                el: this.options.el + ' .edit-container',
                attributeList: attributeList,
                attributeDefs: attributeDefs,
                model: model
            });
        },

        actionSave: function () {
            var editView = this.getView('edit');
            var attrs = editView.fetch();

            editView.model.set(attrs, {silent: true});
            if (editView.validate()) {
                return;
            }

            var attributes = {};
            attributes = editView.model.attributes;

            this.trigger('after:save', attributes);
            return true;
        },
    });
});
