/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope/fields/name', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            this.translatedOptions = {};
            $.each(this.getMetadata().get('scopes') || {}, (scope, defs) => {
                if (defs.acl) {
                    this.translatedOptions[scope] = this.translate(scope, 'scopeNames');
                }
            })

            const sortedEntries = Object.entries(this.translatedOptions).sort((a, b) => {
                return a[1].localeCompare(b[1]);
            });

            this.translatedOptions = Object.fromEntries(sortedEntries);
            this.params.options = Object.keys(this.translatedOptions);

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, `change:${this.name}`, () => {
                if (this.model.get(this.name)) {
                    this.model.set('aclActionList', this.getMetadata().get(`scopes.${this.model.get(this.name)}.aclActionList`));
                } else {
                    this.model.set('aclActionList', null);
                }
            });
        },

    });
});

