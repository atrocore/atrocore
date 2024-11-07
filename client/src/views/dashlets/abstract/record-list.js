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

Espo.define('views/dashlets/abstract/record-list', ['views/dashlets/abstract/base', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        scope: null,

        listView: null,

        _template: '<div class="list-container">{{{list}}}</div>',

        optionsFields: _.extend(_.clone(Dep.prototype.optionsFields), {
            'displayRecords': {
                type: 'enumInt',
                options: [3,4,5,10,15],
            }
        }),

        init: function () {
            this.scope = this.getMetadata().get(['dashlets', this.name, 'entityType']) || this.scope;
            Dep.prototype.init.call(this);
        },

        checkAccess: function () {
            return this.getAcl().check(this.scope, 'read');
        },

        getSearchData: function () {
            return this.getOption('searchData');
        },

        getSearchWhere() {
            return this.getOption('entityFilter');
        },

        afterRender: function () {
            this.getCollectionFactory().create(this.scope, function (collection) {
                var searchData = this.getSearchData();

                var searchManager = this.searchManager = new SearchManager(collection, 'list', null, this.getDateTime(), searchData);

                if (!this.scope) {
                    this.$el.find('.list-container').html(this.translate('selectEntityType', 'messages', 'DashletOptions'));
                    return;
                }

                if (!this.checkAccess()) {
                    this.$el.find('.list-container').html(this.translate('No Access'));
                    return;
                }

                if (this.collectionUrl) {
                    collection.url = this.collectionUrl;
                }

                this.collection = collection;
                collection.sortBy = this.getOption('sortBy') || this.collection.sortBy;
                collection.asc = this.getOption('asc') || this.collection.asc;

                if (this.getOption('sortDirection') === 'asc') {
                    collection.asc = true;
                } else if (this.getOption('sortDirection') === 'desc') {
                    collection.asc = false;
                }

                collection.maxSize = this.getOption('displayRecords');

                let searchWhere = this.getSearchWhere();
                if (searchWhere && searchWhere.where) {
                    collection.where = searchWhere.where;
                } else {
                    collection.where = searchManager.getWhere();
                }

                this.createView('list', 'views/record/list', {
                    collection: collection,
                    el: this.getSelector() + ' .list-container',
                    pagination: this.getOption('pagination') ? 'bottom' : false,
                    checkboxes: false,
                    showMore: true,
                    layoutName: 'listSmall',
                    skipBuildRows: true
                }, function (view) {
                    view.getSelectAttributeList(function (selectAttributeList) {
                        if (selectAttributeList) {
                            collection.data.select = selectAttributeList.join(',');
                        }
                        collection.fetch();
                    }.bind(this));
                });

            }, this);
        },

        actionRefresh: function () {
            if (!this.collection) return;

            this.collection.where = this.searchManager.getWhere();
            this.collection.fetch();
        },

    });
});

