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
        editTemplate: 'layout/fields/edit',

        setup: function () {
            if (!this.params.translation) {
                this.params.translation = 'Admin.layouts';
            }
            Dep.prototype.setup.call(this);

            this.setAvailableOptions()

            this.listenTo(this.model, 'change:entity', () => {
                this.setAvailableOptions()
            })
        },

        setAvailableOptions() {
            this.params.options = this.getAvailableOptions()
            this.setupTranslation()
            this.setOptionList(this.params.options)
            this.params.groupOptions = {
                "view": this.params.options.filter(o => !(["relationships", "sidePanelsDetail"].includes(o))),
                "viewGroup": ["relationships", "sidePanelsDetail"]
            }
            this.params.translatedGroupOptions = this.translate("viewType", "groupOptions", "Layout")
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
            for (const layout of this.getMetadata().get(['clientDefs', this.model.get('entity'), 'additionalLayouts']) || []) {
                optionList.push(layout)
            }

            return optionList
        }
    });
});

