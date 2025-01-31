/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/modals/feedback', 'views/modal', function (Modal) {
    return Modal.extend({
        template: 'modals/feedback',
        openFromIcon: false,
        events: {
            'click a.close': function () {
                this.actionClose();
            }
        },
        setup(){
            Modal.prototype.setup.call(this);
        },
        data(){
          return {
              formLink: 'https://www.atrocore.com/provide-feedback'
          }
        },
        afterRender(){
            Modal.prototype.setup.call(this);
            this.$el.find('.modal-dialog').css('width','740px')
            this.$el.find('.modal-body').css({'paddingTop': '0px', 'height': '100vh'})
        }
    })
});