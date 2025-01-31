/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/dashboard-layout', 'views/layout-profile/modals/navigation',
    (Dep) => Dep.extend({
        className: 'full-page-modal',
        fullHeight: true,
        setup() {
            Dep.prototype.setup.call(this);
            this.buttonList = [
                {
                    name: "Save",
                    label: "Save",
                    style: "primary"
                },
                {
                    name: "Cancel",
                    label: "Cancel",
                }
            ]
        }
    })
);
