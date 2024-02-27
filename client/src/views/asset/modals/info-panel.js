/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/modals/info-panel', 'view', function (Dep) {
    return Dep.extend({

        template: "asset/modals/info-panel",

        setup() {
            this._createTypeDropBox();
        },

        events: {
            'change .field[data-name="type"] > select': function (e) {
                this.model.set("type", $(e.currentTarget).val());
            }
        },

        _createTypeDropBox() {
            let data = this.getMetadata().get("entityDefs.Asset.fields.type.options");
            this.model.set("type", data[0]);

            this.createView("type", "views/fields/enum", {
                model: this.model,
                el: this.options.el + ' .field[data-name="type"]',
                prohibitedEmptyValue: true,
                defs: {
                    name: 'type',
                    params: {
                        options: this.getMetadata().get("entityDefs.Asset.fields.type.options")
                    }
                },
                mode: 'edit'
            });
        },

        setReadOnly() {
            this.getView("type").setReadOnly();
        }
    });
});