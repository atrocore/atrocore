/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/panel-navigation', 'views/record/panel-navigation',
    Dep => Dep.extend({

        actionScrollToPanel(name) {
            if (!name) {
                return;
            }
            const element = document.getElementById(name);
            element.scrollIntoView({behavior: 'smooth', block: 'start'});
        },

        isPanelClosed(name) {
            return false;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            if(this.panelList.length) {
                this.$el.parent().css('position', 'fixed');
                this.$el.parent().css('top', $('.modal-header').css('height'));
                $('.detail > .row').css('margin-top', '120px');
            }else{
                this.$el.hide();
                $('.detail > .row').css('margin-top', '80px');
            }
        }
    })
);
