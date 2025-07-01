/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/modals/dashboard-layout', 'views/layout-profile/modals/dashboard-layout',
    (Dep) => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList.push({
                name: "resetToDefault",
                label: "Reset to Default",
            })
        },

        actionResetToDefault() {
            this.notify('Saving...');

            this.getPreferences().set('dashboardLayout', null);
            this.getPreferences().set('dashletsOptions', null);

            this.getPreferences().save({patch: true}).then(() => {
                this.notify('Saved', 'success');
                this.close();
                this.getPreferences().trigger('update');
            });
        }
    })
);