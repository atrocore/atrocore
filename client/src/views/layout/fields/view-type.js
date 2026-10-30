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
            this.selectAvailableOption()

            this.listenTo(this.model, 'change:entity', () => {
                this.setupGroups()
                this.setupTranslation()
                this.selectAvailableOption()
                this.reRender()
            })
        },

        // e.g. the list layout is not available for a subtype
        selectAvailableOption() {
            const options = this.getAvailableOptions()
            if (options.length && !options.includes(this.model.get(this.name))) {
                this.model.set(this.name, options[0])
            }
        },

        setupGroups() {
            this.params.options = this.getAvailableOptions()
            // the options depend on the entity, the ones remembered for disabling options must not be restored
            this.originalOptionList = null
            this.params.groupOptions = [
                {
                    name: "view",
                    options: this.params.options.filter(o => !(["relationships", "navigation", "insights", "selectionRelations"].includes(o)))
                },
                {
                    name: "viewGroup",
                    options: ["relationships", "navigation", "insights", "selectionRelations"].filter(o => this.params.options.includes(o))
                }
            ]
        },

        getAvailableOptions() {
            const optionList = [
                "list",
                "detail",
                "summary",
                "relationships",
                "navigation",
                "insights",
                "selection",
                "selectionRelations"
            ]
            if (this.getMetadata().get(['clientDefs', this.model.get('entity'), 'kanbanViewMode'])) {
                optionList.push("kanban")
            }
            Object.keys(this.getMetadata().get(['clientDefs', this.model.get('entity'), 'additionalLayouts']) || {})
                .forEach(layout => {
                    optionList.push(layout)

                })

            return this.filterOptionsForSubtypes(optionList)
        },

        /**
         * A record of an entity with subtypes is shown with the layouts of its subtype, while a subtype has no list of
         * its own: each of them gets only the layouts it is shown with.
         */
        filterOptionsForSubtypes(optionList) {
            const recordLayouts = ["detail", "summary", "relationships", "navigation", "insights"]
            const scopeDefs = this.getMetadata().get(['scopes', this.model.get('entity')]) || {}

            // the list layout of a subtype is used by the relationship panels of the entities related to the subtype
            if (scopeDefs.type === 'Subtype') {
                return optionList.filter(o => recordLayouts.includes(o) || o === 'list')
            }

            if (scopeDefs.hasSubtypes) {
                return optionList.filter(o => !recordLayouts.includes(o))
            }

            return optionList
        }
    });
});

