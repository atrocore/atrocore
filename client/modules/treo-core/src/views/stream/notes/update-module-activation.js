/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/stream/notes/update-module-activation', 'views/stream/note',
    Dep =>  Dep.extend({

        template: 'treo-core:stream/notes/update-module-activation',

        isEditable: false,

        isRemovable: false,

        messageName: null,

        data() {
            let data = Dep.prototype.data.call(this);
            data.package = this.getPackage();
            return data;
        },

        init() {
            this.messageName = this.model.get('data').disabled ? 'deactivateModule' : 'activateModule';
            Dep.prototype.init.call(this);
        },

        setup() {
            this.createMessage();
        },

        getPackage() {
            let locale = this.getPreferences().get('language') || this.getConfig().get('language');
            let package = (this.model.get('data') || {}).package || {};
            let names = (package.extra || {}).name || {};
            return {
                id: package.name,
                name: names[locale] || names['default'] || package.name,
                version: package.version
            };
        }
    })
);

