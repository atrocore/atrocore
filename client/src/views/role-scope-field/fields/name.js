/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope-field/fields/name', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.prepareOptionsList();
            this.listenTo(this.model, 'change:roleScopeName', () => {
                this.model.set(this.name, null);
                this.prepareOptionsList();
                this.reRender();
            });

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

        prepareOptionsList() {
            const scope = this.model.get('roleScopeName');
            const forbiddenList = [
                'id',
                'associatedItemRelations',
                'associatingItemRelations',
                'associatedItems',
                'associatingItems'
            ]

            this.params.options = [''];
            this.translatedOptions = { '': '' };

            this.getFieldManager().getScopeFieldList(scope).forEach(field => {
                if (forbiddenList.includes(field)) {
                    return;
                }

                // a hasOne link is a virtual field of an one-to-one relation - the value is stored in the foreign
                // field of the related entity, so the access has to be configured for that field
                if (this.getMetadata().get(['entityDefs', scope, 'links', field, 'type']) === 'hasOne') {
                    return;
                }

                this.translatedOptions[field] = this.translate(field, 'fields', scope);
            })

            const sortedEntries = Object.entries(this.translatedOptions).sort((a, b) => {
                return a[1].localeCompare(b[1]);
            });

            this.translatedOptions = Object.fromEntries(sortedEntries);
            this.params.options = Object.keys(this.translatedOptions);
        },

    });
});

