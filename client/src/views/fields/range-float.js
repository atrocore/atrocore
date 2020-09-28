

Espo.define('views/fields/range-float', ['views/fields/range-int', 'views/fields/float'], function (Dep, Float) {

    return Dep.extend({

        type: 'rangeFloat',

        validations: ['required', 'float', 'range', 'order'],

        validateFloat: function () {
            var validate = function (name) {
                if (isNaN(this.model.get(name))) {
                    var msg = this.translate('fieldShouldBeFloat', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, '[name="'+name+'"]');
                    return true;
                }
            }.bind(this);

            var result = false;
            result = validate(this.fromField) || result;
            result = validate(this.toField) || result;
            return result;
        },

        parse: function (value) {
            return Float.prototype.parse.call(this, value);
        },

        formatNumber: function (value) {
            return Float.prototype.formatNumber.call(this, value);
        },

    });
});

