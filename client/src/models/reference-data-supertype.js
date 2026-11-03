/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * A record of a reference data entity with subtypes is created via the subtype of its type.
 */
Espo.define('models/reference-data-supertype', 'model', Dep => Dep.extend({

    sync(method, model, options) {
        if (method === 'create' && model.get('type')) {
            options = options || {};
            options.url = this.getSubtypeName(model.get('type'));
        }

        return Dep.prototype.sync.call(this, method, model, options);
    },

    getSubtypeName(type) {
        const suffix = String(type)
            .split(/[-_]/)
            .map(part => part.charAt(0).toUpperCase() + part.slice(1))
            .join('');

        return `${this.name}Type${suffix}`;
    },

}));
