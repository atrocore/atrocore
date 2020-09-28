


Espo.define('views/admin/layouts/modals/edit-attributes', ['views/modal', 'model'], function (Dep, Model) {

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

            if (this.options.languageCategory) {
                this.header = this.translate(this.options.name, this.options.languageCategory || 'fields', this.options.scope);
            } else {
                this.header = false;
            }

            var attributeList = Espo.Utils.clone(this.options.attributeList || []);

            var filteredAttribueList = [];
            attributeList.forEach(function (item) {
                if ((this.options.attributeDefs[item] || {}).readOnly) {
                    return;
                }
                filteredAttribueList.push(item);
            }, this);

            attributeList = filteredAttribueList;

            this.createView('edit', 'views/admin/layouts/record/edit-attributes', {
                el: this.options.el + ' .edit-container',
                attributeList: attributeList,
                attributeDefs: this.options.attributeDefs,
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
