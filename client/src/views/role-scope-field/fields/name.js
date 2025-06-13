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
        },

        prepareOptionsList() {
            const scope = this.model.get('roleScopeName');

            this.params.options = [''];
            this.translatedOptions = {'': ''};

            this.getFieldManager().getScopeFieldList(scope).forEach(field => {
                if (!['id'].includes(field)) {
                    this.translatedOptions[field] = this.translate(field, 'fields', scope);
                }
            })

            const sortedEntries = Object.entries(this.translatedOptions).sort((a, b) => {
                return a[1].localeCompare(b[1]);
            });

            this.translatedOptions = Object.fromEntries(sortedEntries);
            this.params.options = Object.keys(this.translatedOptions);
        },

    });
});

