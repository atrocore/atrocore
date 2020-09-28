

Espo.define('views/modals/kanban-move-over', 'views/modal', function (Dep) {

    return Dep.extend({

        template: 'modals/kanban-move-over',

        data: function () {
            return {
                optionDataList: this.optionDataList
            };
        },

        events: {
            'click [data-action="move"]': function (e) {
                var value = $(e.currentTarget).data('value');
                this.moveTo(value);
            }
        },

        setup: function () {
            this.scope = this.model.name;
            var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);

            this.statusField = this.options.statusField;

            this.header = '';
            this.header += this.getLanguage().translate(this.scope, 'scopeNames');
            if (this.model.get('name')) {
                this.header += ' &raquo; ' + Handlebars.Utils.escapeExpression(this.model.get('name'));
            }
            this.header = iconHtml + this.header;

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.optionDataList = [];

            (this.getMetadata().get(['entityDefs', this.scope, 'fields', this.statusField, 'options']) || []).forEach(function (item) {
                this.optionDataList.push({
                    value: item,
                    label: this.getLanguage().translateOption(item, this.statusField, this.scope)
                });
            }, this);
        },

        moveTo: function (status) {
            var attributes = {};
            attributes[this.statusField] = status;
            this.model.save(attributes, {patch: true}).then(function () {
                Espo.Ui.success(this.translate('Done'));
            }.bind(this));
            this.close();
        }

    });
});
