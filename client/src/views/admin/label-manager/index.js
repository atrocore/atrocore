

Espo.define('views/admin/label-manager/index', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/label-manager/index',

        scopeList: null,

        scope: null,

        language: null,

        languageList: null,

        data: function () {
            return {
                scopeList: this.scopeList,
                languageList: this.languageList,
                scope: this.scope,
                language: this.language
            };
        },

        events: {
            'click [data-action="selectScope"]': function (e) {
                var scope = $(e.currentTarget).data('name');
                this.getRouter().checkConfirmLeaveOut(function () {
                    this.selectScope(scope);
                }, this);
            },
            'change select[data-name="language"]': function (e) {
                var language = $(e.currentTarget).val();
                this.getRouter().checkConfirmLeaveOut(function () {
                    this.selectLanguage(language);
                }, this);
            }
        },

        setup: function () {
            this.languageList = this.getConfig().get('languageList') || ['en_US'];
            this.languageList.sort(function (v1, v2) {
                return this.getLanguage().translateOption(v1, 'language').localeCompare(this.getLanguage().translateOption(v2, 'language'));
            }.bind(this));

            this.wait(true);

            this.ajaxPostRequest('LabelManager/action/getScopeList').then(function (scopeList) {
                this.scopeList = scopeList;

                this.scopeList.sort(function (v1, v2) {
                    return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
                }.bind(this));

                this.scopeList = this.scopeList.filter(function (scope) {
                    if (scope === 'Global') return;
                    if (this.getMetadata().get(['scopes', scope])) {
                        if (this.getMetadata().get(['scopes', scope, 'disabled'])) return;
                    }
                    return true;
                }, this);

                this.scopeList.unshift('Global');

                this.wait(false);
            }.bind(this));


            this.scope = this.options.scope || 'Global';
            this.language = this.options.language || this.getConfig().get('language');

            this.once('after:render', function () {
                this.selectScope(this.scope, true);
            }, this);
        },

        selectLanguage: function (language) {
            this.language = language;

            if (this.scope) {
                this.getRouter().navigate('#Admin/labelManager/scope=' + this.scope + '&language=' + this.language, {trigger: false});
            } else {
                this.getRouter().navigate('#Admin/labelManager/language=' + this.language, {trigger: false});
            }

            this.createRecordView();
        },

        selectScope: function (scope, skipRouter) {
            this.scope = scope;

            if (!skipRouter) {
                this.getRouter().navigate('#Admin/labelManager/scope=' + scope + '&language=' + this.language, {trigger: false});
            }

            this.$el.find('[data-action="selectScope"]').removeClass('disabled').removeAttr('disabled');
            this.$el.find('[data-name="'+scope+'"][data-action="selectScope"]').addClass('disabled').attr('disabled', 'disabled');

            this.createRecordView();
        },

        createRecordView: function () {
            Espo.Ui.notify(this.translate('loading', 'messages'));

            this.createView('record', 'views/admin/label-manager/edit', {
                el: this.getSelector() + ' .language-record',
                scope: this.scope,
                language: this.language,
            }, function (view) {
                view.render();
                Espo.Ui.notify(false);
                $(window).scrollTop(0);

            }, this);
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Label Manager', 'labels', 'Admin'));
        },
    });
});


