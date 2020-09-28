

Espo.define('views/admin/dynamic-logic/conditions/field-types/link-parent', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        fetch: function () {
            var valueView = this.getView('value');

            var item;

            if (valueView) {
                valueView.fetchToModel();
            }

            if (this.type === 'equals' || this.type === 'notEquals') {
                var values = {};
                values[this.field + 'Id'] = valueView.model.get(this.field + 'Id');
                values[this.field + 'Name'] = valueView.model.get(this.field + 'Name');
                values[this.field + 'Type'] = valueView.model.get(this.field + 'Type');

                if (this.type === 'equals') {
                    item = {
                        type: 'and',
                        value: [
                            {
                                type: 'equals',
                                attribute: this.field + 'Id',
                                value: valueView.model.get(this.field + 'Id')
                            },
                            {
                                type: 'equals',
                                attribute: this.field + 'Type',
                                value: valueView.model.get(this.field + 'Type')
                            }
                        ],
                        data: {
                            field: this.field,
                            type: 'equals',
                            values: values
                        }
                    };
                } else {
                    item = {
                        type: 'or',
                        value: [
                            {
                                type: 'notEquals',
                                attribute: this.field + 'Id',
                                value: valueView.model.get(this.field + 'Id')
                            },
                            {
                                type: 'notEquals',
                                attribute: this.field + 'Type',
                                value: valueView.model.get(this.field + 'Type')
                            }
                        ],
                        data: {
                            field: this.field,
                            type: 'notEquals',
                            values: values
                        }
                    };
                }
            } else {
                item = {
                    type: this.type,
                    attribute: this.field + 'Id',
                    data: {
                        field: this.field
                    }
                };
            }

            return item;
        }

    });

});

