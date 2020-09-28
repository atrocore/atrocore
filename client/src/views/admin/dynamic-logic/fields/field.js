

Espo.define('views/admin/dynamic-logic/fields/field', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        getFieldList: function () {
            var fields = this.getMetadata().get('entityDefs.' + this.options.scope + '.fields');

            var filterList = Object.keys(fields).filter(function (field) {
                var fieldType = fields[field].type || null;
                if (!fieldType) return;

                if (!this.getMetadata().get(['clientDefs', 'DynamicLogic', 'fieldTypes', fieldType])) return;

                return true;
            }, this);

            filterList.push('id');

            filterList.sort(function (v1, v2) {
                return this.translate(v1, 'fields', this.options.scope).localeCompare(this.translate(v2, 'fields', this.options.scope));
            }.bind(this));

            return filterList;
        },

        setupTranslatedOptions: function () {
            this.translatedOptions = {};
            this.params.options.forEach(function (item) {
                var field = item;
                this.translatedOptions[item] = this.translate(field, 'fields', this.options.scope);
            }, this);
        },

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            this.params.options = this.getFieldList();
            this.setupTranslatedOptions();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.$element && this.$element[0] && this.$element[0].selectize) {
                this.$element[0].selectize.focus();
            }
        }

    });

});

