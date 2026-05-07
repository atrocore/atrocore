/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/quick-actions', 'views/fields/multi-enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.setupOptions();
        },

        labelMap: {
            quickView:   'View',
            quickEdit:   'Edit',
            quickRemove: 'Remove',
            // 'notInherit'
        },

        setupOptions() {
            this.params.options = Object.keys(this.labelMap);
            this.translatedOptions = {};
            this.params.options.forEach(action => {
                this.translatedOptions[action] = this.translate(this.labelMap[action]);
            });
        },

    });
});
