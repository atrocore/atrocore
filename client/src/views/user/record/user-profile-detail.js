/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user/record/user-profile-detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setup() {

            Dep.prototype.setup.call(this);

            this.buttonList.push({
                name: 'access',
                label: 'Access',
                style: 'default'
            });

            this.dropdownItemList = [];

            if (!this.getConfig().get('resetPasswordViaEmailOnly', false)) {
                this.dropdownItemList.push({
                    name: 'changePassword',
                    label: this.translate('Change Password', 'labels', 'User'),
                    style: 'default'
                });
            }

            this.dropdownItemList.push({
                name: 'resetPassword',
                label: this.translate('Reset Password', 'labels', 'User'),
                style: 'default'
            });
        },

    });

});
