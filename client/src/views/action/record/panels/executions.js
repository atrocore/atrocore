/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/record/panels/executions', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            this.defs.layout = [
                {
                    "name": "name",
                    "link": true
                },
                {
                    "name": "type"
                },
                {
                    "name": "status"
                },
                {
                    "name": "startedAt"
                },
                {
                    "name": "finishedAt"
                }
            ];

            if (['create', 'createOrUpdate'].includes(this.model.get('type'))) {
                this.defs.layout.push({
                    "name": "createdCount",
                    "notSortable": true,
                    "width": 10
                });
            }

            if (['update', 'createOrUpdate'].includes(this.model.get('type'))) {
                this.defs.layout.push({
                    "name": "updatedCount",
                    "notSortable": true,
                    "width": 10
                });
            }

            if (['create', 'update', 'createOrUpdate'].includes(this.model.get('type'))) {
                this.defs.layout.push({
                    "name": "failedCount",
                    "notSortable": true,
                    "width": 10
                });
            }

            Dep.prototype.setup.call(this);
        },

        actionAllLogs(data) {
            this.getStorage().set('listQueryBuilder', 'ActionExecutionLog', {
                textFilter: '',
                primary: null,
                presetName: null,
                bool: {},
                queryBuilder: {
                    condition: 'AND',
                    rules: [
                        {
                            id: 'actionExecutionId',
                            field: 'actionExecutionId',
                            type: 'string',
                            operator: 'in',
                            value: [data.id],
                            data: {
                                nameHash: {
                                    [data.id]: data.name
                                }
                            }
                        }
                    ],
                    valid: true
                },
                queryBuilderApplied: true
            });

            window.open(`#ActionExecutionLog`, '_blank');
        },

    })
);
