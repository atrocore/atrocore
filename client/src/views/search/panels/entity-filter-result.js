/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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