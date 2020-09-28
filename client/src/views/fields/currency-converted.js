

Espo.define('views/fields/currency-converted', 'views/fields/currency', function (Dep) {

    return Dep.extend({

        data: function () {
            var currencyValue = this.getConfig().get('defaultCurrency');

            var data = Dep.prototype.data.call(this);

            data.currencyValue = currencyValue;
            data.currencySymbol = this.getMetadata().get(['app', 'currency', 'symbolMap', currencyValue]) || '';

            return data;
        }

    });
});
