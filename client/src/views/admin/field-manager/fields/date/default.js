

Espo.define('views/admin/field-manager/fields/date/default', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            if (data[this.name] === '') {
                data[this.name] = null;
            }

            return data;
        },

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            var value = this.model.get(this.name);
            if (this.params.options && value && !~(this.params.options).indexOf(value)) {
                this.params.options.push(value);
            }
        }

    });

});
