


Espo.define('views/modals/mass-convert-currency', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'modals/mass-convert-currency',

        data: function () {
            return {

            };
        },

        buttonList: [
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        setup: function () {
            this.header = this.translate(this.options.entityType, 'scopeNamesPlural') + ' &raquo ' + this.translate('convertCurrency', 'massActions');
            this.addButton({
                name: 'convert',
                text: this.translate('Update'),
                style: 'danger'
            }, true);

            var model = this.model = new Model();
            model.set('currency', this.getConfig().get('defaultCurrency'));
            model.set('baseCurrency', this.getConfig().get('baseCurrency'));
            model.set('currencyRates', this.getConfig().get('currencyRates'));
            model.set('currencyList', this.getConfig().get('currencyList'));

            this.createView('currency', 'views/fields/enum', {
                model: model,
                params: {
                    options: this.getConfig().get('currencyList')
                },
                name: 'currency',
                el: this.getSelector() + ' .field[data-name="currency"]',
                mode: 'edit',
                labelText: this.translate('Convert to')
            });

            this.createView('baseCurrency', 'views/fields/enum', {
                model: model,
                params: {
                    options: this.getConfig().get('currencyList')
                },
                name: 'baseCurrency',
                el: this.getSelector() + ' .field[data-name="baseCurrency"]',
                mode: 'detail',
                labelText: this.translate('baseCurrency', 'fields', 'Settings'),
                readOnly: true
            });

            this.createView('currencyRates', 'views/settings/fields/currency-rates', {
                model: model,
                name: 'currencyRates',
                el: this.getSelector() + ' .field[data-name="currencyRates"]',
                mode: 'edit',
                labelText: this.translate('currencyRates', 'fields', 'Settings')
            });
        },

        actionConvert: function () {
            this.disableButton('convert');

            this.getView('currency').fetchToModel();
            this.getView('currencyRates').fetchToModel();

            var currency = this.model.get('currency');
            var currencyRates = this.model.get('currencyRates');

            var hasWhere = !this.options.ids || this.options.ids.length == 0;

            this.ajaxPostRequest(this.options.entityType + '/action/massConvertCurrency', {
                field: this.options.field,
                currency: currency,
                ids: this.options.ids || null,
                where: hasWhere ? this.options.where : null,
                selectData: hasWhere ? this.options.selectData : null,
                byWhere: this.options.byWhere,
                targetCurrency: currency,
                currencyRates: currencyRates,
                baseCurrency: this.getConfig().get('baseCurrency')
            }).then(function (result) {
                this.trigger('after:update', result.count);
                this.close();
            }.bind(this)).fail(function () {
                this.enableButton('convert');
            }.bind(this));
        }

    });
});
