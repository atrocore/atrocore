/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail-bottom', 'views/record/detail-bottom', function (Dep) {

    return Dep.extend({
        setup () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'selection-view-mode:change', (mode) => {
                this.panelList.forEach(p => {
                    if(p.name === 'selectionRecords') {
                        let view = this.getView(p.name);
                        p.actionList = [];
                        if(view) {
                            p.view = 'views/record/panels/relationship';
                            if(mode === 'compare') {
                                p.view = 'views/selection/record/panels/selection-record/compare'
                            }else if(mode === 'merge') {
                                p.view = 'views/selection/record/panels/selection-record/merge'
                            }
                            view.trigger('panel:rebuild', p);
                        }
                        return true;
                    }
                })
            })
        }
    });
});
