
Espo.define('views/dashlets/fields/records/entity-type', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.on('change', function () {
                var o = {
                    primaryFilter: null,
                    boolFilterList: [],
                    title: this.translate('Records', 'dashlets'),
                    sortBy: null,
                    sortDirection: 'asc'
                };
                o.expandedLayout = {
                    rows: []
                };
                var entityType = this.model.get('entityType');
                if (entityType) {
                    o.title = this.translate(entityType, 'scopeNamesPlural');
                    o.sortBy = this.getMetadata().get(['entityDefs', entityType, 'collection', 'sortBy']);
                    var asc = this.getMetadata().get(['entityDefs', entityType, 'collection', 'asc']);
                    if (asc) {
                        o.sortDirection = 'asc';
                    } else {
                        o.sortDirection = 'desc';
                    }
                    o.expandedLayout = {
                        rows: [[{name: "name", link: true, scope: entityType}]]
                    };
                }

                this.model.set(o);
            }, this);
        },

        setupOptions: function () {
            this.params.options =  Object.keys(this.getMetadata().get('scopes')).filter(function (scope) {
                if (this.getMetadata().get('scopes.' + scope + '.disabled')) return;
                if (!this.getAcl().checkScope(scope, 'read')) return;
                if (!this.getMetadata().get(['scopes', scope, 'entity'])) return;
                if (!this.getMetadata().get(['scopes', scope, 'object'])) return;

                return true;
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNames').localeCompare(this.translate(v2, 'scopeNames'));
            }.bind(this));

            this.params.options.unshift('');
        }

    });

});
