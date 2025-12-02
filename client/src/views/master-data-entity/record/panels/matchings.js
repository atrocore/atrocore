/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/master-data-entity/record/panels/matchings', 'views/record/panels/relationship',
    Dep => Dep.extend({

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
            let params = {
                queryBuilder: {
                    condition: 'AND',
                    rules: [
                        {
                            id: 'sourceEntity',
                            field: 'sourceEntity',
                            value: [this.model.id],
                            type: 'string',
                            operator: 'in'
                        }
                    ],
                    valid: true
                },
                queryBuilderApplied: true
            };

            this.getStorage().set('listQueryBuilder', this.scope, params);
            window.open(`#${this.scope}`, '_blank');
        },

        setFilter(filter) {
            this.collection.where = [{
                type: "equals",
                attribute: "sourceEntity",
                value: this.model.id
            }];
        },

    })
);