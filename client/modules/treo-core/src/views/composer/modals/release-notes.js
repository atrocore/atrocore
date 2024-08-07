/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/modals/release-notes', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:composer/modals/release-notes',

        setup() {
            Dep.prototype.setup.call(this);

            this.setupHeader();
            this.setupButtonList();
        },

        setupHeader() {
            this.header = this.translate('showReleaseNotes', 'labels', 'Composer');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'close',
                    label: 'Close'
                }
            ];
        },

        data() {
            return {
                notes: this.options.notes
            };
        }
    })
);