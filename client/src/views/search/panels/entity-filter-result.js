/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

Espo.define('views/search/panels/entity-filter-result', ['views/record/panels/relationship', 'views/search/search-filter-opener'],
    (Dep, SearchFilterOpener) => Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-view-only',

        readOnly: true,

        additionalBoolFilterList: [],

        boolFilterData: {},


        setup() {
            this.defs.create = false;
            this.defs.select = false;
            this.defs.unlinkAll = false;

            Dep.prototype.setup.call(this);

            if(!this.defs.hideShowFullList && !this.getPreferences().get('hideShowFullList')) {
                this.actionList.push({
                    label: 'showFullList',
                    action: 'showFullList'
                });
            }

            this.buttonList.unshift({
                title: this.translate('openSearchFilter'),
                action: 'openSearchFilter',
                html: this.getFilterButtonHtml()
            });

        },

        getLayoutRelatedScope() {
            return null;
        },

        actionShowFullList(data) {
            this.getStorage().set('listQueryBuilder', this.scope, this.model.get('data').whereData || {});
            window.open(`#${this.scope}`, '_blank');
        },

        setFilter(filter) {
            let data = this.model.get('data') || {};
            this.collection.where = data.where || [];
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            $('.panel-entityFilterResult button[data-action="openSearchFilter"]').html(this.getFilterButtonHtml());
        },

        getFilterButtonHtml(field = 'data'){
            return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, field);
        },

        openSearchFilter(scope = null, where = [], callback = null) {
            SearchFilterOpener.prototype.open.call(this, scope, where, callback,  this.additionalBoolFilterList, this.boolFilterData);
        },

    })
);