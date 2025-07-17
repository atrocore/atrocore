/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/record/panels/nested-attributes', 'views/record/panels/relationship',
    Dep => Dep.extend({

        boolFilterData: {
            notParentCompositeAttribute: function () {
                return this.model.id;
            },

            notLinkedWithCurrent: function () {
                return this.model.id;
            },

            onlyForEntity: function () {
                return this.model.get('entityId');
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'prepareAttributesForCreateRelated', (params, link, prepareAttributeCallback) => {
                if (link === 'nestedAttributes') {
                    prepareAttributeCallback({
                        entityId: this.model.get('entityId'),
                        entityName: this.model.get('entityName')
                    })
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();
            if (this.model.get('type') === 'composite') {
                this.$el.parent().show();
            }
        }

    })
);