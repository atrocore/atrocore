

Espo.define('views/inbound-email/fields/test-connection', 'views/email-account/fields/test-connection', function (Dep) {

    return Dep.extend({

        url: 'InboundEmail/action/testConnection',

     });

});

