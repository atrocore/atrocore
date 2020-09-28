
Espo.define('views/import/index', 'view', function (Dep) {

    return Dep.extend({

        template: 'import/index',

        data: function () {
            return {

            };
        },

        formData: null,

        fileContents: null,

        setup: function () {
            this.entityType = this.options.entityType || false;
        },

        changeStep: function (num, result) {
            this.createView('step', 'views/import/step' + num.toString(), {
                el: this.options.el + ' > .import-container',
                entityType: this.entityType,
                formData: this.formData,
                result: result
            }, function (view) {
                view.render();
            });
        },

        afterRender: function () {
            this.changeStep(1);
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Import', 'labels', 'Admin'));
        }

    });
});
