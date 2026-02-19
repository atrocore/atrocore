/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/allowed-options', 'views/fields/extensible-multi-enum-dropdown',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                this.listenTo(this.model, 'change:extensibleEnumId change:attributeExtensibleEnumId', () => {
                    this.model.set(this.name, null);
                    this.prepareOptionsList();
                    this.reRender();
                });
            },

            getExtensibleEnumId() {
                return this.model.get('attributeExtensibleEnumId') || this.model.get('extensibleEnumId');
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                this.$el.parent().hide();

                if (this.getExtensibleEnumId() && !['link', 'linkMultiple'].includes(this.model.get('type'))) {
                    this.$el.parent().show();
                }
            },

        });

    });