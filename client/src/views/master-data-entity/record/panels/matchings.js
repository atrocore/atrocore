/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/master-data-entity/record/panels/matchings', ['views/record/panels/relationship', 'search-manager'],
    (Dep, SearchManager) => Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-view-and-edit',

        setup() {
            this.scope = 'Matching';
            this.url = 'Matching';

            this.model.defs.links.matchings = {
                entity: this.scope,
                type: "hasMany"
            }

            this.defs.create = false;
            this.defs.select = false;
            this.defs.unlinkAll = false;

            Dep.prototype.setup.call(this);

            this.actionList.push({
                label: 'showFullList',
                action: 'showFullList'
            });
        },

        actionShowFullList(data) {
            this.getStorage().set('listQueryBuilder', this.scope, this.getWhereDataForFilter());
            window.open(`#${this.scope}`, '_blank');
        },

        setFilter(filter) {
            let searchManager = new SearchManager(this.collection, 'matchings', null, this.getDateTime());
            searchManager.update({...this.getWhereDataForFilter()});

            this.collection.where = searchManager.getWhere();
        },

        getWhereDataForFilter() {
            return {
                queryBuilder: {
                    condition: 'OR',
                    rules: [
                        {
                            id: 'entity',
                            field: 'entity',
                            value: [this.model.id],
                            type: 'string',
                            operator: 'in'
                        },
                        {
                            id: 'masterEntity',
                            field: 'masterEntity',
                            value: [this.model.id],
                            type: 'string',
                            operator: 'in'
                        }
                    ],
                    valid: true
                },
                queryBuilderApplied: true
            };
        },

    })
);