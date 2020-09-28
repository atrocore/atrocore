

Espo.define('views/fields/autoincrement', 'views/fields/int', function (Dep) {

    return Dep.extend({

        type: 'autoincrement',

        validations: [],

        inlineEditDisabled: true,

        readOnly: true,

        disableFormatting: true,

        parse: function (value) {
            value = (value !== '') ? value : null;
            if (value !== null) {
                 if (value.indexOf('.') !== -1 || value.indexOf(',') !== -1) {
                     value = NaN;
                 } else {
                     value = parseInt(value);
                 }
            }
            return value;
        },

        fetch: function () {
            return {};
        },
    });
});

