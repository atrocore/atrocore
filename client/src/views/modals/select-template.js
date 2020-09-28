

Espo.define('views/modals/select-template', ['views/modals/select-records', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        multiple: false,

        header: false,

        createButton: false,

        searchPanel: false,

        scope: 'Template',

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        loadSearch: function () {
            Dep.prototype.loadSearch.call(this);

            this.searchManager.setAdvanced({
                entityType: {
                    type: 'equals',
                    value: this.options.entityType
                }
            });

            this.collection.where = this.searchManager.getWhere();
        }
    });
});

