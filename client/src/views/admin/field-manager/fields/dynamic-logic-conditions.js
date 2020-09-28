

Espo.define('views/admin/field-manager/fields/dynamic-logic-conditions', 'views/fields/base', function (Dep) {

    return Dep.extend({

        detailTemplate: 'admin/field-manager/fields/dynamic-logic-conditions/detail',

        editTemplate: 'admin/field-manager/fields/dynamic-logic-conditions/edit',

        events: {
            'click [data-action="editConditions"]': function () {
                this.edit();
            }
        },

        data: function () {
        },

        setup: function () {
            this.conditionGroup = Espo.Utils.cloneDeep((this.model.get(this.name) || {}).conditionGroup || []);
            this.scope = this.params.scope || this.options.scope;
            this.createStringView();
        },

        createStringView: function () {
            this.createView('conditionGroup', 'views/admin/dynamic-logic/conditions-string/group-base', {
                el: this.getSelector() + ' .top-group-string-container',
                itemData: {
                    value: this.conditionGroup
                },
                operator: 'and',
                scope: this.scope
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

        edit: function () {
            this.createView('modal', 'views/admin/dynamic-logic/modals/edit', {
                conditionGroup: this.conditionGroup,
                scope: this.scope
            }, function (view) {
                view.render();

                this.listenTo(view, 'apply', function (conditionGroup) {
                    this.conditionGroup = conditionGroup;

                    this.createStringView();
                }, this);
            }, this);
        },

        fetch: function () {
            var data = {};
            data[this.name] = {
                conditionGroup: this.conditionGroup
            };

            if (this.conditionGroup.length === 0) {
                data[this.name] = null;
            }

            return data;
        }
    });

});
