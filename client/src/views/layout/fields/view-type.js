/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/fields/view-type', 'views/fields/enum', function (Dep) {

    return Dep.extend({
        setup: function () {
            this.params.translation = 'Admin.layouts';
            this.params.groupTranslation = 'Layout.groups.viewType'

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entity', () => {
                this.setupGroups()
                this.setupTranslation()
                this.reRender()
            })
        },

        setupGroups() {
            this.params.options = this.getAvailableOptions()
            this.params.groups = {
                "view": this.params.options.filter(o => !(["relationships", "sidePanelsDetail"].includes(o))),
                "viewGroup": ["relationships", "sidePanelsDetail"]
            }
        },

        getAvailableOptions() {
            const optionList = [
                "list",
                "detail",
                "relationships",
                "sidePanelsDetail",
            ]
            if (this.getMetadata().get(['clientDefs', this.model.get('entity'), 'kanbanViewMode'])) {
                optionList.push("kanban")
            }
            Object.keys(this.getMetadata().get(['clientDefs', this.model.get('entity'), 'additionalLayouts']) || {})
                .forEach(layout => {
                    optionList.push(layout)

                })

            return optionList
        }
    });
});

