/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/ui-handler/fields/trigger-action', 'views/fields/extensible-enum-dropdown',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.changeOptions()
            this.listenTo(this.model, 'change:type', () => {
                this.changeOptions()
            });
        },

        getDisabledOptions() {
            return []
        },

        changeOptions() {
            this.disableOptions(this.getDisabledOptions())
        },

    })
);
