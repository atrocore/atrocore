
Espo.define('views/dashlets/fields/records/sort-by', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityType', function () {
                this.setupOptions();
                this.reRender();
            }, this);
        },

        setupOptions: function () {
            var entityType = this.model.get('entityType');
            var scope = entityType;

            if (!entityType) {
                this.params.options = [];
                return;
            }
            var fieldDefs = this.getMetadata().get('entityDefs.' + scope + '.fields') || {};

            var orderableFieldList = Object.keys(fieldDefs).filter(function (item) {
                if (fieldDefs[item].notStorable) {
                    return false;
                }
                return true;
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'fields', scope).localeCompare(this.translate(v2, 'fields', scope));
            }.bind(this));

            var translatedOptions = {};
            orderableFieldList.forEach(function (item) {
                translatedOptions[item] = this.translate(item, 'fields', scope);
            }, this);

            this.params.options = orderableFieldList;
            this.translatedOptions = translatedOptions;
        }

    });

});
