

Espo.define('views/fields/varchar-column', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        searchTypeList: ['startsWith', 'contains', 'equals', 'endsWith', 'like', 'isEmpty', 'isNotEmpty'],

        fetchSearch: function () {
            var type = this.$el.find('[name="'+this.name+'-type"]').val() || 'startsWith';

            var data;

            if (~['isEmpty', 'isNotEmpty'].indexOf(type)) {
                if (type == 'isEmpty') {
                    data = {
                        typeFront: type,
                        where: {
                            type: 'or',
                            value: [
                                {
                                    type: 'columnIsNull',
                                    field: this.name,
                                },
                                {
                                    type: 'columnEquals',
                                    field: this.name,
                                    value: ''
                                }
                            ]
                        }
                    }
                } else {
                    data = {
                        typeFront: type,
                        where: {
                            type: 'and',
                            value: [
                                {
                                    type: 'columnNotEquals',
                                    field: this.name,
                                    value: ''
                                },
                                {
                                    type: 'columnIsNotNull',
                                    field: this.name,
                                    value: null
                                }
                            ]
                        }
                    }
                }
                return data;
            } else {
                var value = this.$element.val().toString().trim();
                value = value.trim();
                if (value) {
                    data = {
                        value: value,
                        type: 'column' . Espo.Utils.upperCaseFirst(type),
                        data: {
                            type: type,
                            value: value
                        }
                    }
                    return data;
                }
            }
            return false;
        }

    });
});

