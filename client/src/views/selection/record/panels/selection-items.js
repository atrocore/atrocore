/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/panels/selection-items', 'views/record/panels/relationship', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            if(this.getAcl().check('Selection','edit')) {
                this.buttonList.push({
                    title: 'Create',
                    action: this.defs.createAction || 'createRelated',
                    link: this.link,
                    acl: 'create',
                    aclScope: this.scope,
                    html: '<i class="ph ph-plus"></i>',
                    data: {
                        link: this.link,
                    }
                });
            }
        },

        actionCreateRelated (data) {
            this.getParentView().getParentView().getParentView().actionAddItem();
        }
    });
});
