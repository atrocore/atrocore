

Espo.define('views/merge', 'views/main', function (Dep) {

    return Dep.extend({

        template: 'merge',

        el: '#main',

        scope: null,

        name: 'Merge',

        headerView: 'views/header',

        recordView: 'views/record/merge',

        setup: function () {
            this.models = this.options.models;

            this.setupHeader();
            this.setupRecord();
        },

        setupHeader: function () {
            this.createView('header', this.headerView, {
                model: this.model,
                el: '#main > .page-header'
            });
        },

        setupRecord: function () {
            this.createView('body', this.recordView, {
                el: '#main > .body',
                models: this.models,
                collection: this.collection
            });
        },

        getHeader: function () {
            var html = '<a href="#' + this.models[0].name + '">' + this.getLanguage().translate(this.models[0].name, 'scopeNamesPlural') + '</a>';
            html += ' &raquo ';
            html += this.getLanguage().translate('merge');
            return html;
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Merge'));
        },
    });
});

