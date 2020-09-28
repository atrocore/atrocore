

Espo.define('views/lead-capture/opt-in-confirmation-expired', ['view', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'lead-capture/opt-in-confirmation-expired',

        setup: function () {
            var model = new Model();

            this.resultData = this.options.resultData;
        },

        data: function () {
            var data = {
                defaultMessage: this.getLanguage().translate('optInConfirmationExpired', 'messages', 'LeadCapture')
            };
            return data;
        }

    });
});
