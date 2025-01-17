/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/background/fields/image', 'views/fields/file', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:imageName', () => {
                this.model.set('name', this.model.get('imageName'));
                this.model.set('code', this.model.get('imageName'));
            });
        },

        afterFileUpload(view) {
            view.dialog.close();
        },

    });
});
