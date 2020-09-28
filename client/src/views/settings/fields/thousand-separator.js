
Espo.define('views/settings/fields/thousand-separator', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        validations: ['required', 'thousandSeparator'],

        validateThousandSeparator: function () {
            if (this.model.get('thousandSeparator') == this.model.get('decimalMark')) {
                var msg = this.translate('thousandSeparatorEqualsDecimalMark', 'messages', 'Admin');
                this.showValidationMessage(msg);
                return true;
            }

        }

    });

});
