/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/multi-upload', ["view", "lib!crypto"], function (Dep) {
    return Dep.extend({
        template      : "asset/multi-upload",
        size          : {},
        attachmentHash: [],
        
        events: _.extend({
            'change input[data-name="upload"]': function (e) {
                this._uploadFiles(e.currentTarget.files);
            }
        }, Dep.prototype.events),
        
        setup() {
            this.attachmentHash = [];
            
            Dep.prototype.setup.call(this);
        },
        
        _uploadFiles(files) {
            let maxUploadCount = this.getMetadata().get("app.fileStorage.maxUploadFiles");
            if (files.length > maxUploadCount) {
                this.notify(this.translate("File limit", "exceptions", "Asset"), "error");
                return false;
            }
            
            let pList = [];
            for (let i = 0; i < files.length; i++) {
                let result = this._createFile(files[i]);
                if (result !== false) {
                    pList.push(result);
                }
            }
            
            Promise.all(pList).then(r => {
                this.collection.trigger("upload:done", r);
            }).catch(r => {
                this.collection.trigger("upload:done", r);
            });
        },
        
        _sizeValidate(size) {
            return true;
        },
        
        _createFile(file) {
            let sizeValidate = this._sizeValidate((
                file.size / 1024
            ));
            
            if (!sizeValidate) {
                this.notify("Size limit", "error");
                return false;
            }
            
            return new Promise((resolve, reject) => {
                this.getModelFactory().create('Attachment', function (model) {
                    let fileReader    = new FileReader();
                    fileReader.onload = function (e) {
                        if (this._isDuplicate(e)) {
                            this.notify("Is Duplicate", "error");
                            resolve();
                        } else {
                            model.set('name', file.name);
                            model.set('type', file.type || 'text/plain');
                            model.set('role', 'Attachment');
                            model.set('size', file.size);
                            model.set('relatedType', "Asset");
                            model.set('file', e.target.result);
                            model.set('field', 'file');
                            model.set('modelAttributes', this.model);
                            model.save({}, {timeout: 0}).then(function () {
                                this.collection.push(model);
                                resolve();
                            }.bind(this)).fail(function () {
                                resolve();
                            }.bind(this));
                        }
                    }.bind(this);
                    fileReader.readAsDataURL(file);
                    
                }.bind(this));
            });
        },
        
        _isDuplicate(e) {
            let hash = CryptoJS.MD5(e.currentTarget.result).toString();
            
            if (this.attachmentHash.find(i => hash === i)) {
                return true;
            }
            
            this.attachmentHash.push(hash);
            
            return false;
        }
    });
});