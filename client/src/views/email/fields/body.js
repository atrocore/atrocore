
Espo.define('views/email/fields/body', 'views/fields/wysiwyg', function (Dep) {

    return Dep.extend({

        useIframe: true,

        getAttributeList: function () {
            return ['body', 'bodyPlain'];
        }

    });
});
