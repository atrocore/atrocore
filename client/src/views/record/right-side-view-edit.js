/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/right-side-view-edit', 'views/record/edit', function (Dep) {

    return Dep.extend({
        template: 'record/right-side-view',

        middleView: 'views/record/right-side-view-middle',

        layoutName: 'rightSideView',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'after:change-mode', (mode) => {
                if(mode === this.mode) {
                    return;
                }
                if(mode === 'edit') {
                    this.setEditMode();
                }else{
                    this.setDetailMode()
                }
            })
        },
        afterRender: function () {
            this.initListenToInlineMode();
        }
    });
});
