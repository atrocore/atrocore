
Espo.define('views/dashlets/fields/records/bool-filter-list', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityType', function () {
                this.setupOptions();
                this.reRender();
            }, this);
        },

        setupOptions: function () {
            var entityType = this.model.get('entityType');
            if (!entityType) {
                this.params.options = [];
                return;
            }

            var filterList = this.getMetadata().get(['clientDefs', entityType, 'boolFilterList']) || [];
            this.params.options = [];

            filterList.forEach(function (item) {
                    if (typeof item === 'object' && item.name) {
                    if (item.accessDataList) {
                        if (!Espo.Utils.checkAccessDataList(item.accessDataList, this.getAcl(), this.getUser(), null, true)) {
                            return false;
                        }
                    }
                    this.params.options.push(item.name);
                    return;
                }
                this.params.options.push(item);
            }, this);

            if (this.getMetadata().get(['scopes', entityType, 'stream']) && this.getAcl().checkScope(entityType, 'stream')) {
                this.params.options.push('followed');
            }

            this.translatedOptions = {};
            this.params.options.forEach(function (item) {
                this.translatedOptions[item] = this.translate(item, 'boolFilters', entityType);
            }, this);
        }

    });

});
