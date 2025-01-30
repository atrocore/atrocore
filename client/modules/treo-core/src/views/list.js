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

        setupSearchPanel() {
            let hiddenBoolFilterList = this.getMetadata().get(`clientDefs.${this.scope}.hiddenBoolFilterList`) || [];
            let searchView = this.getMetadata().get(`clientDefs.${this.scope}.recordViews.search`) || this.searchView;

            this.createView('search', searchView, {
                collection: this.collection,
                el: '#main > main > .page-header .row .search-container',
                searchManager: this.searchManager,
                scope: this.scope,
                viewMode: this.viewMode,
                viewModeList: this.viewModeList,
                hiddenBoolFilterList: hiddenBoolFilterList,
            }, function (view) {
                view.render();
                this.listenTo(view, 'reset', function () {
                    this.resetSorting();
                }, this);

                if (this.viewModeList.length > 1) {
                    this.listenTo(view, 'change-view-mode', this.switchViewMode, this);
                }
            }.bind(this));
        },

        setupSearchManager: function () {
            var collection = this.collection;

            var searchManager = new SearchManager(collection, 'list', this.getStorage(), this.getDateTime(), this.getSearchDefaultData());
            searchManager.scope = this.scope;

            if (this.options.params.showFullListFilter) {
                searchManager.set(_.extend(searchManager.get(), {advanced: Espo.Utils.cloneDeep(this.options.params.advanced)}));
            }

            searchManager.loadStored();

            collection.where = searchManager.getWhere();
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