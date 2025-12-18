/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/filter-action-execution', 'views/fields/link',
    Dep => Dep.extend({

        foreignScope: 'ActionExecution',

        searchTypeList: ['isOneOf', 'isNotOneOf'],

        searchScope: null,

        setup() {
            this.idName = this.name;
            this.searchScope = this.model.defs.fields[this.name].scope;
            Dep.prototype.setup.call(this);
        },

        getSelectFilters: function () {
            if (this.searchScope) {
                return [{
                    type: 'equals',
                    attribute: 'entityName',
                    value: this.searchScope
                }];
            }
        },

        chooseMultipleOnSearch() {
            return false;
        },

        getFilterName(type = null) {
            return this.filterName ?? this.name;
        }

    })
);
