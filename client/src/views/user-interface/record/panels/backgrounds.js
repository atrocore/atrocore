/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-interface/record/panels/backgrounds', 'views/record/panels/relationship', Dep => {

    return Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-no-unlink',

        setup() {
            this.defs.create = false;
            this.defs.select = false;

            this.url = 'Background/action/list';

            Dep.prototype.setup.call(this);

            this.actionList = [];

            this.buttonList.push({
                title: 'Create',
                action: 'createBackground',
                html: '<span class="fas fa-plus"></span>'
            });
        },

        actionCreateBackground() {
            this.notify('Loading...');
            this.createView('quickCreate', 'views/modals/edit', {
                scope: 'Background',
                fullFormDisabled: true
            }, view => {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    this.collection.fetch();
                    this.model.trigger('after:relate', 'variables');
                });
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().css({"padding-bottom": "120px"});
        },

    });

});