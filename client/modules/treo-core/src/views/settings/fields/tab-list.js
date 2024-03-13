/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/settings/fields/tab-list', 'class-replace!treo-core:views/settings/fields/tab-list',
    Dep => Dep.extend({

        setup() {
            this.prepareDefaultTabList();

            Dep.prototype.setup.call(this);
        },

        prepareDefaultTabList() {
            let tabList = (Espo.Utils.cloneDeep(this.model.get(this.name)) || []).filter(tab => typeof tab !== 'object');
            this.model.set({[this.name]: tabList});
        },

    })
);
