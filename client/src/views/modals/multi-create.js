/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/modals/multi-create', 'views/modal', function (Dep) {
    return Dep.extend({
        saved   : false,
        template: "modals/multi-create",
        scope   : null,
        
        setup() {
            this.scope  = this.options.scope || null;
            this.header = this.getLanguage().translate("Create Assets", 'labels', this.scope);
            
            this.getCollectionFactory().create("Attachments", collection => {
                this.collection = collection;
                this.collection.listenTo(this.collection, "upload:done", () => {
                    if (this.collection.length > 0) {
                        this._afterUploadDone();
                        this._renderAttachmentList();
                    }
                });
            });
            
            this._renderInfoPanel();
            this._renderUpload();
            
            this.listenTo(this, "close", () => {
                if (this.saved) {
                    return true;
                }
                let count = this.collection.length;
                while (count > 0) {
                    this.collection.models[0].destroy();
                    count--;
                }
            });
            
            this.once("after:save", () => {
                this.trigger("after:save");
            });
        },
        
        _renderAttachmentList() {
            this.createView("attachmentList", "views/asset/modals/attachment-list", {
                el        : this.options.el + " .attachment-list",
                collection: this.collection,
                model     : this.model
            }, view => {
                view.render();
            });
        },
        
        _renderUpload() {
            this.createView("upload", "views/asset/multi-upload", {
                model     : this.model,
                collection: this.collection,
                el        : this.options.el + ' div[data-name="upload"]'
            });
        },
        
        _renderInfoPanel() {
            this.getModelFactory().create("CreateAssets", model => {
                this.model = model;
                this.createView("assetInfoPanel", "views/asset/modals/info-panel", {
                    model: this.model,
                    el   : this.options.el + " .info-panel"
                });
            });
        },
        
        _afterUploadDone() {
            this.addButton({
                name : "save",
                label: "Save",
                style: 'primary'
            });
            
            this.addButton({
                name : "cancel",
                label: "Cancel"
            });
            this.getView("assetInfoPanel").setReadOnly();
        },
        
        actionSave() {
            let Promises = [];
            this.collection.forEach(model => {
                model.get("assetModel").setRelate(this.options.relate);
                
                Promises.push(new Promise((resolve, rejected) => {
                    model.get("assetModel").save().then(() => {
                        resolve();
                    }).fail(() => {
                        rejected();
                    });
                }));
            });
            Promise.all(Promises).then(r => {
                this._afterSave();
                this.saved = true;
                this.dialog.close();
            }).catch(r => {
            
            });
        },
        
        _afterSave() {
            this.trigger("after:save");
        }
        
    });
});