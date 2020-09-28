

Espo.define('views/fields/number', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        type: 'number',

        validations: [],

        inlineEditDisabled: true,

        readOnly: true,

        fetch: function () {
            return {};
        },
    });
});

