
Espo.define('views/settings/fields/address-preview', 'views/fields/address', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var mainModel = this.model;
            var model = mainModel.clone();
            model.name = mainModel.name;

            model.set({
                addressPreviewStreet: 'Street',
                addressPreviewPostalCode: 'PostalCode',
                addressPreviewCity: 'City',
                addressPreviewState: 'State',
                addressPreviewCountry: 'Country'
            });

            this.listenTo(mainModel, 'change:addressFormat', function () {
                model.set('addressFormat', mainModel.get('addressFormat'));
                this.reRender();
            }, this);

            this.model = model;
        },

        getAddressFormat: function () {
            return this.model.get('addressFormat') || 1;
        },

    });

});
