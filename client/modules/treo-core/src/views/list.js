/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/list', ['class-replace!treo-core:views/list', 'search-manager'],
    (Dep, SearchManager) => Dep.extend({

        enabledFixedHeader: true,

        prepareRecordViewOptions(options) {
            Dep.prototype.prepareRecordViewOptions.call(this, options);

            options.enabledFixedHeader = this.enabledFixedHeader;
        },

        setupSearchManager: function () {
            var collection = this.collection;

            var searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime(), this.getSearchDefaultData());
            searchManager.scope = this.scope;

            if (this.options.params.showFullListFilter) {
                searchManager.set(_.extend(searchManager.get(), {advanced: Espo.Utils.cloneDeep(this.options.params.advanced)}));
            }

            searchManager.loadStored();

            let savedFilters = searchManager.getSavedFilters();

            if(savedFilters.length) {
                this.ajaxGetRequest('SavedSearch', {
                    collectionOnly: true,
                    where: [{
                        type: 'equals',
                        attribute: 'entityType',
                        value: this.scope
                    }],
                    maxSize: 20
                }, {async: false}).then((result) => {
                    searchManager.savedSearchList = result.list;
                    savedFilters = savedFilters.map(i => result.list.find(item => item.id === i.id)).filter(i => i);
                    searchManager.update({savedFilters});
                });
            }

            this.collection.where = searchManager.getWhere();
            this.searchManager = searchManager;
        },

        setupSorting() {
            var sortingParams = this.getStorage().get('listSorting', this.collection.name);

            if (sortingParams && sortingParams.sortBy && !(sortingParams.sortBy in this.getMetadata().get(['entityDefs', this.collection.name, 'fields']))) {
                this.getStorage().clear('listSorting', this.collection.name);
            }

            Dep.prototype.setupSorting.call(this);
        }
    })
);