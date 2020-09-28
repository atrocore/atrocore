

Espo.define('views/dashlets/emails', 'views/dashlets/abstract/record-list', function (Dep) {

    return Dep.extend({

        name: 'Emails',

        scope: 'Emails',

        rowActionsView: 'views/email/record/row-actions/dashlet',

        listView: 'views/email/record/list-expanded',

        setupActionList: function () {
            if (this.getAcl().checkScope(this.scope, 'create')) {
                this.actionList.unshift({
                    name: 'compose',
                    html: this.translate('Compose Email', 'labels', this.scope),
                    iconHtml: '<span class="fas fa-plus"></span>'
                });
            }
        },

        actionCompose: function () {
            var attributes = this.getCreateAttributes() || {};

            this.notify('Loading...');
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.modalViews.compose') || 'views/modals/compose-email';
            this.createView('modal', viewName, {
                scope: this.scope,
                attributes: attributes,
            }, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.actionRefresh();
                }, this);
            }, this);
        }

    });
});

