/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/preview-template/fields/entity-type', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            const options = this.getEntitiesList();

            this.params.options = options;
            this.translatedOptions = {};
            options.forEach(option => {
                this.translatedOptions[option] = this.translate(option, 'scopeNames');
            });

            Dep.prototype.setup.call(this);
        },

        getEntitiesList() {
            let scopes = this.getMetadata().get('scopes') || {};
            return Object.keys(scopes)
                .filter(scope => scopes[scope].importable && scopes[scope].entity)
                .sort((v1, v2) => this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural')));
        },

    })
);