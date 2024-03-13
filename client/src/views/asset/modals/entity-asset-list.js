/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/modals/entity-asset-list', 'views/modal', function (Dep) {
    return Dep.extend({
        template  : "asset/modals/entity-asset-list",
        items     : [],
        assetTypes: {},
        
        data() {
            return {
                items: this.items
            };
        },
        
        setup() {
            this.header     = this.getLanguage().translate("Create Entity Assets", 'labels', this.scope);
            this.assetTypes = this.options.assetTypes;
            
            this.addButton({
                name : "save",
                label: "Save",
                style: 'primary'
            });
            
            this.addButton({
                name : "cancel",
                label: "Cancel"
            });
            
            this._renderItems();
        },
        
        _renderItems() {
            this.items = [];
            
            this.collection.forEach((model) => {
               
                let viewName = `entityAsset-${model.id}`;
                this.items.push(viewName);
                this.createView(viewName, "views/asset/modals/entity-asset-item", {
                    model: model,
                    el   : this.options.el + ` tr[data-name="${viewName}"]`,
                    assetType : this.assetTypes[model.get("assetId")]
                });
            });
        },
        
        actionSave() {
            if (this.validate()) {
                this.notify('Not valid', 'error');
                return;
            }
            
            this.collection.forEach(model => {
                model.save().then(() => {
                    this.notify('Linked', 'success');
                    this.trigger("after:save");
                    this.dialog.close();
                });
            });
        },
        
        validate() {
            let notValid = false;
            for (let key in this.nestedViews) {
                const view = this.nestedViews[key];
                if (view && typeof view.validate === 'function') {
                    notValid = view.validate() || notValid;
                }
            }
            return notValid;
        }
    });
});