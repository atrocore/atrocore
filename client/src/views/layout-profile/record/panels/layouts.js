/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/record/panels/layouts', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.once('after:render', () => {
                this.setupConfigBtn();
            });
        },

        setupConfigBtn() {
            const $btnGroup = this.$el.parent().find('.panel-heading .btn-group');
            $btnGroup.find('.action.layouts').remove();

            const $button = $('<button type="button" class="btn btn-default btn-sm action layouts" data-action="configureLayouts"><span class="fas fa-cog" style="line-height: 0; font-size: 13px;"></span></button>');
            $btnGroup.append($button);

            $button.on('click', (e) => {
                this.getRouter().navigate('#Admin/layouts?layoutProfileId=' + this.model.get('id'), {trigger: true});
            });
        },
    })
);