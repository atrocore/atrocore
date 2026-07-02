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

            this.listenTo(this.model, 'prepareAttributesForCreateRelated', (attributes, link, cb) => {
                if (link === 'derivativeEntities') {
                    attributes.primaryEntityId = this.model.id;
                    attributes.type = this.model.get('type');
                    cb(attributes)
                }
            })
        },

    })
);

