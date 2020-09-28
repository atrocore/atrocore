

Espo.define('views/fields/array-int', 'views/fields/array', function (Dep) {

    return Dep.extend({

        type: 'arrayInt',

        fetchFromDom: function () {
            var selected = [];
            this.$el.find('.list-group .list-group-item').each(function (i, el) {
                var value = $(el).data('value');
                if (typeof value === 'string' || value instanceof String) {
                    value = parseInt($(el).data('value'));
                }
                selected.push(value);
            });
            this.selected = selected;
        },

        addValue: function (value) {
            value = parseInt(value);
            if (isNaN(value)) {
                return;
            }
            Dep.prototype.addValue.call(this, value);
        },

        removeValue: function (value) {
            value = parseInt(value);
            if (isNaN(value)) {
                return;
            }
            Dep.prototype.removeValue.call(this, value);
        }

    });
});

