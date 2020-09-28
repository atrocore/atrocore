

Espo.define('views/admin/currency', 'views/settings/record/edit', function (Dep) {

    return Dep.extend({

        layoutName: 'currency',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:currencyList', function (model, value, o) {
                if (!o.ui) return;

                var currencyList = Espo.Utils.clone(model.get('currencyList'));
                this.setFieldOptionList('defaultCurrency', currencyList);
                this.setFieldOptionList('baseCurrency', currencyList);

                this.controlCurrencyRatesVisibility();
            }, this);

            this.listenTo(this.model, 'change', function (model, o) {
                if (!o.ui) return;

                if (model.hasChanged('currencyList') || model.hasChanged('baseCurrency')) {
                    var currencyRatesField = this.getFieldView('currencyRates');
                    if (currencyRatesField) {
                        currencyRatesField.reRender();
                    }
                }
            }, this);
            this.controlCurrencyRatesVisibility();
        },

        controlCurrencyRatesVisibility: function () {
            var currencyList = this.model.get('currencyList');
            if (currencyList.length < 2) {
                this.hideField('currencyRates');
            } else {
                this.showField('currencyRates');
            }
        }

    });

});

