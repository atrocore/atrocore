/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/modals/update', 'treo-core:views/composer/modals/install',
    Dep => Dep.extend({

        setupHeader() {
            this.header = this.translate('Edit') + ': ' + this.model.get('name');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
        },

    })
);