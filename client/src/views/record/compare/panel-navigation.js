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
            debugger
            if (!name) {
                return;
            }
            const element = document.getElementById(name);
            element.scrollIntoView({behavior: 'smooth', block: 'start'});
        },

        setPanelList() {
            this.panelList = this.options.panelList;
        },

        isPanelClosed(name) {
            return false;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.$el.parent().css('position', 'fixed');
            this.$el.parent().css('width', 'auto');
            if(this.panelList.length) {
                this.$el.parent().css('top', $('.modal-header').css('height'));
                $('.detail > .row').css('margin-top', '130px');
            }else{
                this.$el.hide();
                $('.detail > .row').css('margin-top', '80px');
            }
        }
    })
);
