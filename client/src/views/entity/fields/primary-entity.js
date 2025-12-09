/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/primary-entity', 'views/fields/link',
    Dep => Dep.extend({

        createDisabled: true,

        selectBoolFilterList: ['fieldsFilter'],

        boolFilterData: {
            fieldsFilter() {
                return {
                    type: ["Base", "Hierarchy"]
                };
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get(this.idName) !== null && ['list', 'detail'].includes(this.mode)) {
                this.$el.html(`<a href="/#MasterDataEntity/view/${this.model.get(this.idName)}">${this.model.get(this.nameName)}</a>`);
            }
        },

    })
);

