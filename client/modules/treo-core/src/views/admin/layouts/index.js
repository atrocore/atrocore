/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/layouts/index', 'class-replace!treo-core:views/admin/layouts/index',
    Dep => Dep.extend({

        template: 'treo-core:admin/layouts/index',

        events: _.extend({}, Dep.prototype.events, {
            'click button[data-action="resetAllToDefault"]': function () {
                this.confirm(this.translate('resetAllToDefaultConfirm', 'messages'), function () {
                    this.resetAllToDefault();
                }, this);
            },
        }),

        resetAllToDefault: function () {
            this.notify('Saving...');
            this.ajaxPostRequest('Layout/action/resetAllToDefault').then(() => {
                this.notify('Done', 'success');
            });
        },

        renderLayoutHeader: function () {
            if (!this.scope) {
                $("#layout-header").html("");
                return;
            }
            $("#layout-header").show().html(this.getLanguage().translate(this.scope, 'scopeNamesPlural') + " &raquo; " + this.getLanguage().translate(this.type, 'layouts', 'Admin'));
        }

    })
);


