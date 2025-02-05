/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            if (this.getUser().isAdmin()) {
                this.buttonList.push({
                    name: 'layouts',
                    label: 'Layouts',
                    action: "layouts"
                });
            }
            Dep.prototype.setup.call(this);
        },

        setupActionItems() {
            if (this.getUser().isAdmin()) {
                if (!this.additionalButtons.find(b => b.name === 'menu')) {
                    this.additionalButtons.push({
                        name: "menu",
                        label: this.translate("Menu", "labels", "LayoutProfile"),
                        action: "editNavigation",
                    })
                }

                if (!this.additionalButtons.find(b => b.name === 'dashboard')) {
                    this.additionalButtons.push({
                        name: "dashboard",
                        label: this.translate("Dashboards", "labels", "LayoutProfile"),
                        action: "editDashboard",
                        cssStyle: "margin-left: 10px"
                    });
                }
            }

            Dep.prototype.setupActionItems.call(this);
        },

        actionLayouts: function () {
            this.getRouter().navigate('#Admin/layouts?layoutProfileId=' + this.model.get('id'), {trigger: true});
        },

        actionEditNavigation: function () {
            this.createView('edit', 'views/layout-profile/modals/navigation', {
                field: 'navigation',
                model: this.model
            }, view => {
                view.render();
            });
        },

        actionEditDashboard: function () {
            this.createView('edit', 'views/layout-profile/modals/dashboard-layout', {
                field: 'dashboardLayout',
                model: this.model,
            }, view => {
                view.render();
            });
        }
    });
});

