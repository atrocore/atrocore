/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/fields/dashboard-layout', 'views/layout-profile/fields/navigation', function (Dep) {

    return Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        inlineEdit: function () {
            this.createView('edit', 'views/layout-profile/modals/dashboard-layout', {
                field: this.name,
                model: this.model,
                avoidSaving: this.initMode === 'edit'
            }, view => {
                view.render();
            });
        },
    });
});

