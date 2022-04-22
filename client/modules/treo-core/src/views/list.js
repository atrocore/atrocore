/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
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
                el: '#main > .page-header .row .search-container',
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

            let filters = searchManager.get();
            if (filters.advanced) {
                for (let filter in filters.advanced) {
                    let field = filter.split('-').shift();

                    if (!this.getMetadata().get(['entityDefs', this.scope, 'fields', field])) {
                        delete filters.advanced[filter]
                    }
                }

                searchManager.set(filters);
            }

            collection.where = searchManager.getWhere();
            this.searchManager = searchManager;
        },

        setupSorting() {
            var sortingParams = this.getStorage().get('listSorting', this.collection.name);

            if (sortingParams && sortingParams.sortBy && !(sortingParams.sortBy in this.getMetadata().get(['entityDefs', this.collection.name, 'fields']))) {
                this.getStorage().clear('listSorting', this.collection.name);
            }

            Dep.prototype.setupSorting.call(this);
        },

        afterRender() {
            let footer = $('#footer');

            if (footer.length && !$('.catalog-tree-panel').length) {
                footer.removeClass('is-collapsed');
            }

            Dep.prototype.afterRender.call(this);
        }
    })
);