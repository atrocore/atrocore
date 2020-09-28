

Espo.define('views/dashlets/records', 'views/dashlets/abstract/record-list', function (Dep) {

    return Dep.extend({

        name: 'Records',

        scope: null,

        rowActionsView: 'views/record/row-actions/view-and-edit',

        listView: 'views/email/record/list-expanded',

        init: function () {
            Dep.prototype.init.call(this);
            this.scope = this.getOption('entityType');
        },

        getSearchData: function () {
            var data = {
                primary: this.getOption('primaryFilter')
            };

            var bool = {};
            (this.getOption('boolFilterList') || []).forEach(function (item) {
                bool[item] = true;
            }, this);

            data.bool = bool;

            return data;
        },

        setupActionList: function () {
            var scope = this.getOption('entityType');
            if (scope && this.getAcl().checkScope(scope, 'create')) {
                this.actionList.unshift({
                    name: 'create',
                    html: this.translate('Create ' + scope, 'labels', scope),
                    iconHtml: '<span class="fas fa-plus"></span>',
                    url: '#' + scope + '/create'
                });
            }
        },

    });
});

