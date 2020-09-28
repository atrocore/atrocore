

Espo.define('treo-core:views/fields/bool-with-inline-label', 'views/fields/bool',
    Dep => Dep.extend({

        editTemplate: 'treo-core:fields/bool-with-inline-label/base',

        events: _.extend({
            'click label': function (e) {
                e.preventDefault();
                this.$element.prop('checked', !this.model.get(this.name));
                this.trigger('change');
            }
        }, Dep.prototype.events),

        data() {
            return _.extend({
                label: this.options.label || this.translate(this.name, 'fields', this.scope),
            }, Dep.prototype.data.call(this));
        }

    })
);