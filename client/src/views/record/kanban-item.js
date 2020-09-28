

Espo.define('views/record/kanban-item', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/kanban-item',


        data: function () {
            return {
                layoutDataList: this.layoutDataList,
                rowActionsDisabled: this.rowActionsDisabled
            };
        },

        events: {

        },

        setup: function () {
            this.itemLayout = this.options.itemLayout;
            this.rowActionsView = this.options.rowActionsView;
            this.rowActionsDisabled = this.options.rowActionsDisabled;

            this.layoutDataList = [];

            this.itemLayout.forEach(function (item, i) {
                var name = item.name;
                var key = name + 'Field';
                var o = {
                    name: name,
                    isAlignRight: item.align === 'right',
                    isLarge: item.isLarge,
                    isFirst: i === 0,
                    key: key
                };
                this.layoutDataList.push(o);

                var viewName = item.view || this.model.getFieldParam(name, 'view');
                if (!viewName) {
                    var type = this.model.getFieldType(name) || 'base';
                    viewName = this.getFieldManager().getViewName(type);
                }

                var mode = 'list';
                if (item.link) {
                    mode = 'listLink';
                }

                this.createView(key, viewName, {
                    model: this.model,
                    name: name,
                    mode: mode,
                    readOnly: true,
                    el: this.getSelector() + ' .field[data-name="'+name+'"]'
                });

            }, this);

            if (!this.rowActionsDisabled) {
                var acl =  {
                    edit: this.getAcl().checkModel(this.model, 'edit'),
                    delete: this.getAcl().checkModel(this.model, 'delete')
                };
                this.createView('itemMenu', this.rowActionsView, {
                    el: this.getSelector() + ' .item-menu-container',
                    model: this.model,
                    acl: acl,
                    statusFieldIsEditable: this.options.statusFieldIsEditable
                });
            }
        }

    });
});
