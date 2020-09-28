

Espo.define('views/admin/field-manager/fields/options', 'views/fields/array', function (Dep) {

    return Dep.extend({

        maxItemLength: 100,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.translatedOptions = {};
            var list = this.model.get(this.name) || [];
            list.forEach(function (value) {
                this.translatedOptions[value] = this.getLanguage().translateOption(value, this.options.field, this.options.scope);
            }, this);

            this.model.fetchedAttributes.translatedOptions = this.translatedOptions;
        },

        getItemHtml: function (value) {
            var valueSanitized = this.getHelper().stripTags(value);
            var translatedValue = this.translatedOptions[value] || valueSanitized;

            translatedValue = translatedValue.replace(/"/g, '&quot;');

            var valueInternal = valueSanitized.replace(/"/g, '-quote-');

            var html = '' +
            '<div class="list-group-item link-with-role form-inline" data-value="' + valueInternal + '">' +
                '<div class="pull-left" style="width: 92%; display: inline-block;">' +
                    '<input name="translatedValue" data-value="' + valueInternal + '" class="role form-control input-sm pull-right" value="'+translatedValue+'">' +
                    '<div>' + valueSanitized + '</div>' +
                '</div>' +
                '<div style="width: 8%; display: inline-block; vertical-align: top;">' +
                    '<a href="javascript:" class="pull-right" data-value="' + valueInternal + '" data-action="removeValue"><span class="fas fa-times"></a>' +
                '</div><br style="clear: both;" />' +
            '</div>';

            return html;
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            if (!data[this.name].length) {
                data[this.name] = false;
                data.translatedOptions = {};
                return data;
            }

            data.translatedOptions = {};
            (data[this.name] || []).forEach(function (value) {
                var valueSanitized = this.getHelper().stripTags(value);
                var valueInternal = valueSanitized.replace(/"/g, '-quote-');
                var translatedValue = this.$el.find('input[name="translatedValue"][data-value="'+valueInternal+'"]').val() || value;

                translatedValue = translatedValue.toString();

                data.translatedOptions[value] = translatedValue;
            }, this);

            return data;
        }

    });

});
