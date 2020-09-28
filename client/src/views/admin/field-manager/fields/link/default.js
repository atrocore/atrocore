

Espo.define('views/admin/field-manager/fields/link/default', 'views/fields/link', function (Dep) {

    return Dep.extend({

        data: function () {
            var defaultAttributes = this.model.get('defaultAttributes') || {};
            var nameValue = defaultAttributes[this.options.field + 'Name'] || null;
            var idValue = defaultAttributes[this.options.field + 'Id'] || null;

            var data = Dep.prototype.data.call(this);

            data.nameValue = nameValue;
            data.idValue = idValue;

            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.foreignScope = this.getMetadata().get(['entityDefs', this.options.scope, 'links', this.options.field, 'entity']);
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            var defaultAttributes = {};
            defaultAttributes[this.options.field + 'Id'] = data[this.idName];
            defaultAttributes[this.options.field + 'Name'] = data[this.nameName];

            if (data[this.idName] === null) {
                defaultAttributes = null;
            }

            return {
                defaultAttributes: defaultAttributes
            };
        }

    });

});
