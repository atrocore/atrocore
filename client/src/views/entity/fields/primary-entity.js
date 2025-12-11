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

        selectBoolFilterList: ['fieldsFilter', 'onlyForDerivativeEnabled'],

        boolFilterData: {
            fieldsFilter() {
                return {
                    type: [this.model.get('type')]
                };
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.isNew()) {
                this.listenTo(this.model, 'change:type', () => {
                    this.model.set(this.idName, null);
                    this.model.set(this.nameName, null);
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get(this.idName) && ['list', 'detail'].includes(this.mode)) {
                this.$el.html(`<a href="/#MasterDataEntity/view/${this.model.get(this.idName)}">${this.model.get(this.nameName)}</a>`);
            }
        },

    })
);

