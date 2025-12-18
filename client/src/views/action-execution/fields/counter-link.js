/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action-execution/fields/counter-link', 'views/fields/int',
    Dep => Dep.extend({

        events: _.extend({
            'click [data-action="showList"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.actionShowList();
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            this.listScope = this.model.get('listScope');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (['list', 'detail'].includes(this.mode) && this.model.get(this.name) && this.model.get(this.name) > 0) {
                if (!(this.getConfig().get('clickhouse')?.active && this.model.get(this.name) > 65000)) {
                    this.$el.find('.inline-container').html(`<a href="javascript:" data-action="showList" data-name="${this.name}">${this.model.get(this.name)}</a>`);
                }
            }
        },

        actionShowList() {
            const searchFilter = this.getSearchFilter();
            this.getStorage().set('listQueryBuilder', this.listScope, searchFilter);
            window.open(`#${this.listScope}`, '_blank');
        },

        getSearchFilter() {
            return {
                textFilter: '',
                primary: null,
                presetName: null,
                bool: {},
                queryBuilder: {
                    condition: 'AND',
                    rules: [
                        {
                            id: this.name + 'FilterActionExecution',
                            field: this.name + 'FilterActionExecution',
                            type: 'string',
                            operator: 'in',
                            value: [this.model.id],
                            data: {
                                nameHash: {
                                    [this.model.id]: this.model.get('name')
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
