/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/panels/side/download/custom', 'view',
    Dep => {
        return Dep.extend({
            template       : "file/record/panels/side/download/custom",
            downloadModel  : {},
            active         : false,
            
            events: {
                'change input': function (e) {
                    let $el  = $(e.currentTarget);
                    let name = $el.prop("name");
                    this.downloadModel.set(name, $el.val());
                }
            },
            
            data() {
                return {
                    downloadModel: this.downloadModel
                };
            },
            
            setup() {
                Dep.prototype.setup.call(this);

                this._createModel();
                this._createForm();

                this.listenToOnce(this, "after:render", () => {
                    this._changeMode();
                    this._changeFormat();
                });
            },
            
            _createForm() {
                this.createView("width", "views/fields/int", {
                    el    : `${this.options.el} .field[data-name="width"]`,
                    model : this.downloadModel,
                    name  : 'width',
                    mode  : 'edit',
                    params: {
                        min: 0
                    }
                });
                
                this.createView("height", "views/fields/int", {
                    el    : `${this.options.el} .field[data-name="height"]`,
                    model : this.downloadModel,
                    name  : 'height',
                    mode  : 'edit',
                    params: {
                        min: 0
                    }
                });
                
                this.createView("mode", "views/fields/enum", {
                    model               : this.downloadModel,
                    el                  : `${this.options.el} .field[data-name="mode"]`,
                    defs                : {
                        name  : 'mode',
                        params: {
                            options          : ["byWidth", "byHeight", "resize"],
                            translatedOptions: {
                                "resize"  : "Resize",
                                "byWidth" : "Scale by width",
                                "byHeight": "Scale by height"
                            }
                        }
                    },
                    mode                : 'edit',
                    prohibitedEmptyValue: true
                });
                
                this.createView("format", "views/fields/enum", {
                    model: this.downloadModel,
                    el   : `${this.options.el} .field[data-name="format"]`,
                    defs : {
                        name  : "format",
                        params: {
                            options          : ["jpeg", "png"],
                            translatedOptions: {
                                "jpeg": "JPEG",
                                "png" : "PNG"
                            }
                        }
                    },
                    mode : "edit",
                    prohibitedEmptyValue : true
                });

                this.createView("quality", "views/fields/int", {
                    el    : `${this.options.el} .field[data-name="quality"]`,
                    model : this.downloadModel,
                    name  : 'quality',
                    mode  : 'edit',
                    readOnly: false,
                    params: {
                        min: 0,
                        max: 100
                    }
                });
                
            },
            
            hide() {
                this.active = false;
                this.$el.find(".additional-panel").hide();
            },
            
            show() {
                this.active = true;
                this.$el.find(".additional-panel").show();
            },
            
            buildUrl() {
                return `?entryPoint=download&id=${this.model.get('id')}` + "&" +
                    `width=${this.downloadModel.get("width")}` + "&" +
                    `height=${this.downloadModel.get("height")}` + "&" +
                    `quality=${this.downloadModel.get("quality")}` + "&" +
                    `scale=${this.downloadModel.get("mode")}` + "&" +
                    `format=${this.downloadModel.get("format")}` + "&" +
                    `type=custom`;
                
            },
            
            _getFormat() {
                return this.model.get("mimeType") === "image/png" ? "png" : "jpeg";
            },
            
            _createModel() {
                this.getModelFactory().create("downloadModel", model => {
                    model.set("width", this.model.get("width"));
                    model.set("height", this.model.get("height"));
                    model.set("quality", 100);
                    model.set("mode", "byWidth");
                    model.set("format", this._getFormat());
                    
                    this.downloadModel = model;
                    model.listenTo(model, "change:quality", () => {
                        if (parseInt(model.get('quality')) > 100) {
                            model.set("quality", 100);
                        }
                        if (parseInt(model.get('quality')) <= 0) {
                            model.set("quality", 1);
                        }
                    });
                    
                    model.listenTo(model, "change:width", () => {
                        if (parseInt(model.get('width')) < 1) {
                            model.set("width", 1);
                        }
                    });
                    
                    model.listenTo(model, "change:height", () => {
                        if (parseInt(model.get('height')) < 1) {
                            model.set("height", 1);
                        }
                    });
                    
                    model.listenTo(model, "change:mode", () => {
                        this._changeMode();
                    });
                    
                    model.listenTo(model, "change:format", () => {
                        this._changeFormat();
                    });
                });
            },
            
            _changeMode() {
                let heightView = this.getView("height");
                let widthView  = this.getView("width");
                
                switch (this.downloadModel.get("mode")) {
                    case "byWidth" :
                        this._setScaleWidth(heightView, widthView);
                        break;
                    
                    case "byHeight" :
                        this._setScaleHeight(heightView, widthView);
                        break;
                    
                    case "resize" :
                        this._setScaleResize(heightView, widthView);
                        break;
                }
                
                heightView.reRender();
                widthView.reRender();
            },
            
            _setScaleWidth(heightView, widthView) {
                heightView.setMode('detail');
                widthView.setMode('edit');
                
                this.downloadModel.set("height", "");
                if (!this.downloadModel.get("width")) {
                    this.downloadModel.set("width", this.model.get("width"));
                }
            },
            
            _setScaleHeight(heightView, widthView) {
                heightView.setMode('edit');
                widthView.setMode('detail');
                
                this.downloadModel.set("width", "");
                if (!this.downloadModel.get("height")) {
                    this.downloadModel.set("height", this.model.get("height"));
                }
            },
            
            _setScaleResize(heightView, widthView) {
                heightView.setMode('edit');
                widthView.setMode('edit');
                
                if (!this.downloadModel.get("width")) {
                    this.downloadModel.set("width", this.model.get("width"));
                }
                if (!this.downloadModel.get("height")) {
                    this.downloadModel.set("height", this.model.get("height"));
                }
            },
            
            _changeFormat() {
                let qualityView = this.getView("quality");
                if (this.downloadModel.get("format") === "png") {
                    this.downloadModel.set("quality", 100);
                    qualityView.setMode('detail');
                } else {
                    qualityView.setMode('edit');
                }
                
                qualityView.reRender();
            }
        });
    }
);