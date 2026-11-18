/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/scheduled-job/record/detail', ['views/record/detail', 'views/search/search-filter-opener'], (Dep, SearchFilterOpener) => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync', () => {
                this.reRender();
            });

            this.listenTo(this.model, 'change:entity', () => {
                let scope = this.model.get('entity');
                let data = this.model.get('searchFilter') || {};

                if (typeof data.whereScope === 'undefined' || data.whereScope !== scope) {
                    this.model.set('searchFilter', _.extend({}, data, {
                        where: null,
                        whereData: null,
                        whereScope: scope
                    }));
                }
            });
        },

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            if (this.model.id && this.model.get('isActive')) {
                this.additionalButtons.push({
                    action: 'executeNow',
                    label: this.translate('executeNow', 'labels', 'ScheduledJob')
                })
            }

            if (this.model.get('type') === 'Erasure' && this.model.get('entity')) {
                this.additionalButtons.push({
                    tooltip: this.translate('openSearchFilter'),
                    action: 'openSearchFilter',
                    name: 'searchFilter',
                    html: SearchFilterOpener.prototype.getFilterButtonHtml.call(this, 'searchFilter')
                });
            }
        },

        actionExecuteNow() {
            this.ajaxPostRequest(`ScheduledJob/${this.model.id}/executeNow`).then(response => {
                this.notify(this.translate(response ? 'jobLaunched' : 'jobAlreadyExist', 'messages', 'ScheduledJob'), response ? 'success' : 'danger');
                $('button.action[data-action="refresh"][data-panel="jobs"]').click();
            });
        },

        actionOpenSearchFilter() {
            const scope = this.model.get('entity');
            if (!scope || !this.getMetadata().get(['scopes', scope])) {
                this.notify(this.translate('The entity is not valid'), 'error');
                return;
            }

            const searchFilter = this.model.get('searchFilter') || {};
            let whereData = searchFilter.where;
            if (searchFilter.whereData
                && (searchFilter.whereData['queryBuilder']
                    || searchFilter.whereData['bool']
                    || searchFilter.whereData['textFilter']
                    || searchFilter.whereData['savedSearch']
                )
            ) {
                whereData = searchFilter.whereData;
            }

            SearchFilterOpener.prototype.open.call(this, scope, whereData, ({where, whereData}) => {
                this.model.set('searchFilter', _.extend({}, this.model.get('searchFilter'), {
                    where,
                    whereData,
                    whereScope: scope
                }));
                this.notify(this.translate('saving', 'messages'));
                this.model.save({_prev: null}).then(() => this.notify(this.translate('Done'), 'success'));
            });
        }
    });

});
