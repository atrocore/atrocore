/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/storage/fields/path', 'views/fields/varchar',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.get('type') === 'local' && !this.model.get(this.name)) {
                this.model.set(this.name, 'upload/files');
            }
            this.listenTo(this.model, 'change:type', () => {
                if (this.model.get('type') === 'local') {
                    this.model.set(this.name, 'upload/files');
                } else {
                    this.model.set(this.name, null);
                }
            });
        },

    })
);
