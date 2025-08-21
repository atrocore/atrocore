/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/fields/attribute-group-layout-item', 'views/fields/base',
    Dep => Dep.extend({

        readOnly: true,

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const parts = this.name.split('_');
            const attributeGroupId = parts[1] || null;
            if (attributeGroupId) {
                $.each((this.model.get('attributesDefs') || {}), (name, defs) => {
                    if (defs.attributeGroup && defs.attributeGroup.id === attributeGroupId) {
                        this.$el.parent().find('label').html(`<a href="/#AttributeGroup/view/${attributeGroupId}" target="_blank"><span class="label-text" style="font-weight: bold">${defs.attributeGroup.name}</span></a>`);
                    }
                });
            }

            this.$el.css({'border-bottom': '1px solid #e7e7e7', 'min-height': 'auto', 'padding': '0 0 5px'});
        },

    })
);
