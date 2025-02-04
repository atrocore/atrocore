/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-followed-record/fields/entity-type', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.prepareOptionsList();
        },

        prepareOptionsList() {
            this.params.options = [''];
            this.translatedOptions = {};

            $.each(this.getMetadata().get('scopes') || {}, (scope, scopeDefs) => {
                if (!scopeDefs.notStorable && !scopeDefs.streamDisabled && scopeDefs.type && ['Base', 'Hierarchy'].includes(scopeDefs.type)) {
                    this.params.options.push(scope);
                    this.translatedOptions[scope] = this.translate(scope, 'scopeNames', 'Global');
                }
            });
        },

    })
);
