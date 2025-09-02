/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/record-related-changes-in-stream', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type change:relationType', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let shouldShow = this.model.get('type') === 'linkMultiple' && this.model.get('relationType') === 'oneToMany';

            if (shouldShow) {
                this.show()
            } else {
                this.hide()
            }
        }
    });
});
