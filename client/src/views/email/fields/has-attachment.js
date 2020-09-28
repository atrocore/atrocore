

Espo.define('views/email/fields/has-attachment', 'views/fields/base', function (Dep) {

    return Dep.extend({

        listTemplate: 'email/fields/has-attachment/detail',

        detailTemplate: 'email/fields/has-attachment/detail',

    });

});
