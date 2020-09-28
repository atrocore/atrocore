

Espo.define('views/edit', 'views/main', function (Dep) {

    return Dep.extend({

        template: 'edit',

        scope: null,

        name: 'Edit',

        menu: null,

        optionsToPass: ['returnUrl', 'returnDispatchParams', 'attributes', 'rootUrl'],

        headerView: 'views/header',

        recordView: 'views/record/edit',

        setup: function () {
            this.headerView = this.options.headerView || this.headerView;
            this.recordView = this.options.recordView || this.recordView;

            this.setupHeader();
            this.setupRecord();
        },

        setupHeader: function () {
            this.createView('header', this.headerView, {
                model: this.model,
                el: '#main > .header',
                scope: this.scope
            });
        },

        setupRecord: function () {
            var o = {
                model: this.model,
                el: '#main > .record',
                scope: this.scope
            };
            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);
            if (this.options.params && this.options.params.rootUrl) {
                o.rootUrl = this.options.params.rootUrl;
            }
            this.createView('record', this.getRecordViewName(), o);
        },

        getRecordViewName: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.edit') || this.recordView;
        },

        getHeader: function () {
            var html = '';

            var headerIconHtml = this.getHeaderIconHtml();

            var arr = [];

            if (this.options.noHeaderLinks) {
                arr.push(this.getLanguage().translate(this.scope, 'scopeNamesPlural'));
            } else {
                var rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                arr.push(headerIconHtml + '<a href="' + rootUrl + '" class="action" data-action="navigateToRoot">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>');
            }

            if (this.model.isNew()) {
                arr.push(this.getLanguage().translate('create'));
            } else {
                var name = Handlebars.Utils.escapeExpression(this.model.get('name'));

                if (name === '') {
                    name = this.model.id;
                }

                if (this.options.noHeaderLinks) {
                    arr.push(name);
                } else {
                    arr.push('<a href="#' + this.scope + '/view/' + this.model.id + '" class="action">' + name + '</a>');
                }
            }
            return this.buildHeaderHtml(arr);
        },

        updatePageTitle: function () {
            var title;
            if (this.model.isNew()) {
                title = this.getLanguage().translate('Create') + ' ' + this.getLanguage().translate(this.scope, 'scopeNames');
            } else {
                var name = this.model.get('name');
                if (name) {
                    title = name;
                } else {
                    title = this.getLanguage().translate(this.scope, 'scopeNames')
                }
            }
            this.setPageTitle(title);
        },
    });
});


