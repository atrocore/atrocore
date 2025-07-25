/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope-attribute/fields/attribute', 'views/fields/link', Dep => {

    return Dep.extend({

        selectBoolFilterList: ['onlyForEntity'],

        boolFilterData: {
            onlyForEntity() {
                return this.model.get('roleScopeName') || null;
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:readAction', () => {
                if (!this.model.get('readAction')) {
                    this.model.set('editAction', false);
                }
            });

            this.listenTo(this.model, 'change:editAction', () => {
                if (this.model.get('editAction')) {
                    this.model.set('readAction', true);
                }
            });
        },

    });
});

