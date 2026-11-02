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
 * A subtype record is a part of its supertype record: it is deleted together with the supertype record.
 */
Espo.define('models/subtype', 'model', Dep => Dep.extend({

    getSupertype() {
        return ((this.defs.links || {}).supertype || {}).entity || null;
    },

    /**
     * The type of a new record is defined by its subtype, the conditions on the type work in the create form.
     */
    populateDefaults() {
        Dep.prototype.populateDefaults.call(this);

        const prefix = `${this.getSupertype()}Type`;
        if (this.name && this.name.indexOf(prefix) === 0) {
            const suffix = this.name.substring(prefix.length);
            this.set('type', suffix.charAt(0).toLowerCase() + suffix.slice(1), {silent: true});
        }
    },

    sync(method, model, options) {
        if (method === 'delete') {
            options = options || {};
            options.url = `${this.getSupertype()}/${model.get('supertypeId')}`;
        }

        return Dep.prototype.sync.call(this, method, model, options);
    },

}));
