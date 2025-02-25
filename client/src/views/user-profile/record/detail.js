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

            this.additionalButtons.push({
                name: "dashboard",
                label: this.translate("Dashboards", "labels", "LayoutProfile"),
                action: "editDashboard",
                cssStyle: "margin-left: 10px"
            });

            this.additionalButtons.push({
                name: 'favorites',
                label: this.translate('Favorites', 'labels', 'LayoutProfile'),
                action: 'configureFavorites',
                cssStyle: "margin-left: 10px"
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

        actionEditDashboard() {
            this.createView('dashboard', 'views/layout-profile/modals/dashboard-layout', {
                field: 'dashboardLayout',
                model: this.model,
            }, view => {
                view.render();
            });
        },

        actionConfigureFavorites() {
            this.createView('favoritesEdit', 'views/layout-profile/modals/favorites', {
                field: 'favoritesList',
                model: this.model
            }, view => {
                this.notify(false);
                view.render();
            });
        },

        actionChangePassword() {
            this.notify('Loading...');

            this.createView('changePassword', 'views/modals/change-password', {
                userId: this.model.id
            }, view => {
                view.render();
                this.notify(false);

                this.listenToOnce(view, 'changed', () => {
                    if (this.model.id === this.getUser().id) {
                        setTimeout(() => {
                            this.getBaseController().logout();
                        }, 2000);
                    }
                });

            });
        },

        actionResetPassword() {
            $.ajax({
                url: 'User/action/resetPassword',
                type: 'POST',
                data: JSON.stringify({
                    userId: this.model.id
                })
            }).done(() => {
                Espo.Ui.success(this.translate('uniqueLinkHasBeenSent', 'messages', 'User'));
                setTimeout(() => {
                    if (this.model.id === this.getUser().id) {
                        this.getBaseController().logout();
                    }
                }, 2000);
            });
        },

    });
});

