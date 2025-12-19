/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action-execution/fields/counter-link-to-logs', 'views/fields/int',
    Dep => Dep.extend({

        events: _.extend({
            'click [data-action="showList"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.actionShowList();
            }
        }, Dep.prototype.events),

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (['list', 'detail'].includes(this.mode) && this.model.get(this.name) && this.model.get(this.name) > 0) {
                this.$el.find('.inline-container').html(`<a href="javascript:" data-action="showList" data-name="${this.name}">${this.model.get(this.name)}</a>`);
            }
        },

        actionShowList() {
            const searchFilter = this.getSearchFilter();
            this.getStorage().set('listQueryBuilder', 'ActionExecutionLog', searchFilter);
            window.open(`#ActionExecutionLog`, '_blank');
        },

        getSearchFilter() {
            const logType = this.getMetadata().get(`entityDefs.ActionExecution.fields.${this.name}.logType`) || 'error';
            return {
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
                            value: [this.model.id],
                            data: {
                                nameHash: {
                                    [this.model.id]: this.model.get('name')
                                }
                            }
                        },
                        {
                            id: 'type',
                            field: 'type',
                            type: 'string',
                            operator: 'in',
                            value: [logType],
                            data: {
                                nameHash: {
                                    [logType]: this.getLanguage().translateOption(logType, 'type', 'ActionExecutionLog')
                                }
                            }
                        }
                    ],
                    valid: true
                },
                queryBuilderApplied: true
            };
        },
    })
);
