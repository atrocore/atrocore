

Espo.define('controllers/lead-capture-opt-in-confirmation', 'controller', function (Dep) {

    return Dep.extend({

        optInConfirmationSuccess: function (data) {
            var viewName = this.getMetadata().get(['clientDefs', 'LeadCapture', 'optInConfirmationSuccessView']) ||
                'views/lead-capture/opt-in-confirmation-success';

            this.entire(viewName, {
                resultData: data
            }, function (view) {
                view.render();
            });
        },

        optInConfirmationExpired: function (data) {
            var viewName = this.getMetadata().get(['clientDefs', 'LeadCapture', 'optInConfirmationExpiredView']) ||
                'views/lead-capture/opt-in-confirmation-expired';

            this.entire(viewName, {
                resultData: data
            }, function (view) {
                view.render();
            });
        }

    });
});
