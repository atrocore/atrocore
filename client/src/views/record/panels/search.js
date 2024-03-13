/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

Espo.define('views/record/panels/search', ['views/record/panels/bottom', 'search-manager'],
    (Dep, SearchManager) => Dep.extend({

        template: 'record/panels/search',

        searchManager: null,

        collection: null,

        scope: null,

        mode: 'detail',

        searchView: null,

        hasButtons: null,

        setup() {
            Dep.prototype.setup.call(this);

            this.scope = this.scope || this.defs.scope;

            this.hasButtons = this.hasButtons || this.defs.hasButtons || false;

            this.setupSearchPanel();
            this.listenTo(this.model, 'after:change-mode', mode => {
                this.mode = mode;
                this.setupSearchPanel();
            });

            this.listenTo(this.model, 'before:save', () => {
                let filterData = this.getFilterData() || {};
                this.model.set('data', _.extend({}, this.model.get('data'), filterData));
            });
        },

        setupSearchPanel() {
            this.wait(true);
            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;
                this.searchManager = new SearchManager(this.collection, `exportSimpleType`, null, this.getDateTime(), (this.model.get('data') || {}).whereData || [], true);

                let searchView = 'views/record/search';
                if (this.scope === 'Product') {
                    searchView = 'pim:views/product/record/search';
                }

                this.createView('search', searchView, {
                    collection: this.collection,
                    el: `${this.options.el} .search-container`,
                    searchManager: this.searchManager,
                    scope: this.scope,
                    mainModel: this.model,
                    presetFiltersDisabled: true,
                    isLeftDropdown: true,
                    refreshDisabled: true,
                    updateCollectionDisabled: true,
                    disableSavePreset: true,
                    hasButtons: this.hasButtons,
                    mode: this.mode,
                    viewMode: 'list',
                    hiddenBoolFilterList: this.getMetadata().get(`clientDefs.${this.scope}.hiddenBoolFilterList`) || [],
                }, view => {
                    this.searchView = view;
                    view.render();
                    this.wait(false);
                });
            });
        },

        getFilterData() {
            this.searchView.search();
            return {
                where: Espo.Utils.cloneDeep(this.searchView.searchManager.getWhere()),
                whereData: Espo.Utils.cloneDeep(this.searchView.searchManager.get()),
                whereScope: this.searchView.scope,
            }
        },

    })
);
