

Espo.define('treo-core:views/admin/field-manager/fields/options', ['class-replace!treo-core:views/admin/field-manager/fields/options', 'views/fields/array'], function (Dep, Arr) {

    return Dep.extend({

        getItemHtml(value) {
            let valueSanitized = this.getHelper().stripTags(value);
            let translatedValue = this.translatedOptions[value] || valueSanitized;

            translatedValue = translatedValue.replace(/"/g, '&quot;').replace(/\\/g, '&bsol;');

            let valueInternal = valueSanitized.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-');

            return `
                <div class="list-group-item link-with-role form-inline" data-value="${valueInternal}">
                    <div class="pull-left" style="width: 92%; display: inline-block;">
                        <input name="translatedValue" data-value="${valueInternal}" class="role form-control input-sm pull-right" value="${translatedValue}">
                        <div>${valueSanitized}</div>
                    </div>
                    <div style="width: 8%; display: inline-block;">
                        <a href="javascript:" class="pull-right" data-value="${valueInternal}" data-action="removeValue"><span class="fas fa-times"></a>
                    </div>
                    <br style="clear: both;" />
                </div>`;
        },

        fetch() {
            let data = Arr.prototype.fetch.call(this);

            if (!data[this.name].length) {
                data[this.name] = false;
                data.translatedOptions = {};
                return data;
            }

            data.translatedOptions = {};
            (data[this.name] || []).forEach(value => {
                let valueSanitized = this.getHelper().stripTags(value);
                let valueInternal = valueSanitized.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-');
                let translatedValue = this.$el.find('input[name="translatedValue"][data-value="'+valueInternal+'"]').val() || value;
                data.translatedOptions[value] = translatedValue.toString();
            });

            return data;
        },

        fetchFromDom() {
            var selected = [];
            this.$el.find('.list-group .list-group-item').each((i, el) => {
                var value = $(el).data('value').toString();
                value = value.replace(/-quote-/g, '"').replace(/-backslash-/g, '\\');
                selected.push(value);
            });
            this.selected = selected;
        },

    });

});
