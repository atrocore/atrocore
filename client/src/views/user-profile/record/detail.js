/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/record/detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.dropdownItemList = [];

            this.buttonEditList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                    edit: true
                },
                {
                    name: 'cancelEdit',
                    label: 'Cancel',
                    edit: true
                }
            ];
        }

    });
});

