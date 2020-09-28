

Espo.define('treo-core:views/fields/currency', 'class-replace!treo-core:views/fields/currency',
    Dep => Dep.extend({

        detailTemplate: 'treo-core:fields/currency/detail',

        detailTemplate1: 'treo-core:fields/currency/detail-1',

        detailTemplate2: 'treo-core:fields/currency/detail-2',

        listTemplate: 'treo-core:fields/currency/list',

        listTemplate1: 'treo-core:fields/currency/list-1',

        listTemplate2: 'treo-core:fields/currency/list-2',

        data() {
            let data = Dep.prototype.data.call(this);
            data.valueAndCurrency = !!(data.value || data.currencyValue);
            return data;
        },

        fetch: function () {
            let value = this.$element.val();
            value = this.parse(value);

            let data = {};

            data[this.name] = value;
            data[this.currencyFieldName] = this.$currency.val();
            return data;
        },

    })
);