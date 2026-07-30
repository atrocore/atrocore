/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/search/panels/entity-filter-result', ['views/record/panels/relationship', 'views/search/search-filter-opener', 'search-manager'],
    (Dep, SearchFilterOpener, SearchManager) => Dep.extend({

        readOnly: true,

        additionalBoolFilterList: [],

        boolFilterData: {},

        setup() {
            this.defs.create = false;
            this.defs.select = false;
            this.defs.unlinkAll = false;

            this.listenTo(this.model, `change:${this.scope}`, () => {
                debugger
                this.trigger('panel:rebuild');
            })

            if (!this.getAcl().check(this.scope, 'read')) {
                return;
            }

            Dep.prototype.setup.call(this);

            if (!this.defs.hideShowFullList && !this.getPreferences().get('hideShowFullList')) {
                this.actionList.push({
                    label: 'showFullList',
                    action: 'showFullList'
                });

                if (this.getMetadata().get(['clientDefs', this.scope, 'kanbanViewMode'])) {
                    this.actionList.push({
                        label: 'showKanban',
                        action: 'showKanban'
                    });
                }
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

        actionShowKanban(data) {
            this.getStorage().set('listQueryBuilder', this.scope, this.model.get('data').whereData || {});
            window.open(`#${this.scope}/kanban`, '_blank');
        },

        setFilter(filter) {
            let whereData = this.getWhereDataForFilter()
            if (whereData) {
                let searchManager = new SearchManager(this.collection, 'entityFilterResult', null, this.getDateTime());
                searchManager.update({...whereData});
                if (whereData.boolFilterData) {
                    searchManager.boolFilterData = whereData.boolFilterData;
                }
                this.collection.where = searchManager.getWhere();
            } else {
                this.collection.where = this.getWhereForFilter() || [];
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.getAcl().check(this.scope, 'read')) {
                $('.panel-entityFilterResult button[data-action="openSearchFilter"]').html(this.getFilterButtonHtml());
            }
        },

        getNoAccessHtml() {
            const message = this.translate('noAccessToScope', 'exceptions', 'Global')
                .replace('%s', this.translate(this.scope, 'scopeNames'));

            return `
                <div class="list-container">
                    <div class="no-data-container">
                        <span class="ph ph-lock"></span>
                        <span>${message}</span>
                    </div>
                </div>
            `;
        },

        _getHtml(callback) {
            if (!this.getAcl().check(this.scope, 'read')) {
                callback(this.getNoAccessHtml());
                return;
            }

            Dep.prototype._getHtml.call(this, callback);
        },

        getFilterButtonHtml(field = 'data') {
            return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, field);
        },

        openSearchFilter(scope = null, where = [], callback = null) {
            SearchFilterOpener.prototype.open.call(this, scope, where, callback, this.additionalBoolFilterList, this.getBoolFilterData());
        },

        getWhereDataForFilter() {
            let data = this.model.get('data') || {};
            return data.whereData;
        },

        getWhereForFilter() {
            let data = this.model.get('data') || {};
            return data.where;
        },

        getBoolFilterData() {
            let whereData = this.model.get('data')?.whereData || {};

            return {...(this.boolFilterData || {}), ...(whereData.boolFilterData || {})};
        }
    })
);