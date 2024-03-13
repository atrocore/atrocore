/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/record/panels/side/download/renditions', 'view',
    Dep => {
        return Dep.extend({
            template  : "asset/record/panels/side/download/renditions",
            active    : false,
            renditions: {},
            
            setup() {
                this._setRenditions();
            },
            
            hide() {
                this.active = false;
                this.$el.find(".additional-panel").hide();
            },
            
            show() {
                this.active = true;
                this.$el.find(".additional-panel").show();
            },
            
            _setRenditions() {
                this.getCollectionFactory().create("Renditions", collection => {
                    collection.url = `Asset/${this.model.id}/renditions?select=name,fileId,imageId`;
                    collection.fetch().then(() => {
                        this.collection = collection;
                        this.reRender();
                    });
                });
            },
            
            buildUrl() {
                let id           = this.$el.find("select").val();
                let model        = this.collection.get(id);
                let attachmentId = model.get("fileId") || model.get("imageId");
                
                return `?entryPoint=download&showInline=false&id=${attachmentId}`;
            }
        });
    }
);