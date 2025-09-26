/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/modals/custom-download', 'views/modal',
    Dep => {
        return Dep.extend({
            template: 'file/modals/custom-download',

            header: 'Custom Download',

            downloadModel: {},

            fileModel: {},

            events: {
                'change input': function (e) {
                    let $el = $(e.currentTarget);
                    let name = $el.prop("name");
                    this.downloadModel.set(name, $el.val());
                }
            },

            buttonList: [
                {
                    name: 'download',
                    label: 'Download',
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ],

            className: 'dialog custom-download',

            fitHeight: false,

            setup: function () {
                Dep.prototype.setup.call(this);

                this.getModelFactory().create("downloadModel", model => {
                    model.set("width", this.model.get("width"));
                    model.set("height", this.model.get("height"));
                    model.set("quality", 100);
                    model.set("mode", "byWidth");
                    model.set("format", this.model.get("mimeType") === "image/png" ? "png" : "jpeg");

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
                        this.changeMode();
                    });

                    model.listenTo(model, "change:format", () => {
                        this.changeFormat();
                    });
                });

                this.createViews();

                this.listenToOnce(this, "after:render", () => {
                    this.changeMode();
                    this.changeFormat();
                });
            },

            createViews: function () {
                this.createView("width", "views/fields/int", {
                    el: `${this.options.el} .field[data-name="width"]`,
                    model: this.downloadModel,
                    name: 'width',
                    mode: 'edit',
                    params: {
                        min: 0
                    }
                });

                this.createView("height", "views/fields/int", {
                    el: `${this.options.el} .field[data-name="height"]`,
                    model: this.downloadModel,
                    name: 'height',
                    mode: 'edit',
                    params: {
                        min: 0
                    }
                });

                this.createView("mode", "views/fields/enum", {
                    model: this.downloadModel,
                    el: `${this.options.el} .field[data-name="mode"]`,
                    defs: {
                        name: 'mode'
                    },
                    mode: 'edit',
                    params: {
                        required: true,
                        options: ["byWidth", "byHeight", "resize"],
                        translatedOptions: {
                            "resize": "Resize",
                            "byWidth": "Scale by width",
                            "byHeight": "Scale by height"
                        }
                    }
                });

                this.createView("format", "views/fields/enum", {
                    model: this.downloadModel,
                    el: `${this.options.el} .field[data-name="format"]`,
                    defs: {
                        name: "format"
                    },
                    mode: "edit",
                    params: {
                        required: true,
                        options: ["jpeg", "png","webp"],
                        translatedOptions: {
                            "jpeg": "JPEG",
                            "png": "PNG",
                            "webp": "WEBP"
                        }
                    }
                });

                this.createView("quality", "views/fields/int", {
                    el: `${this.options.el} .field[data-name="quality"]`,
                    model: this.downloadModel,
                    name: 'quality',
                    mode: 'edit',
                    readOnly: false,
                    params: {
                        min: 0,
                        max: 100
                    }
                });
            },

            changeMode: function () {
                let heightView = this.getView("height");
                let widthView = this.getView("width");

                switch (this.downloadModel.get("mode")) {
                    case "byWidth" :
                        this.setScaleWidth(heightView, widthView);
                        break;

                    case "byHeight" :
                        this.setScaleHeight(heightView, widthView);
                        break;

                    case "resize" :
                        this.setScaleResize(heightView, widthView);
                        break;
                }

                heightView.reRender();
                widthView.reRender();
            },

            setScaleWidth: function (heightView, widthView) {
                heightView.setMode('detail');
                widthView.setMode('edit');

                this.downloadModel.set("height", "");
                if (!this.downloadModel.get("width")) {
                    this.downloadModel.set("width", this.model.get("width"));
                }
            },

            setScaleHeight: function (heightView, widthView) {
                heightView.setMode('edit');
                widthView.setMode('detail');

                this.downloadModel.set("width", "");
                if (!this.downloadModel.get("height")) {
                    this.downloadModel.set("height", this.model.get("height"));
                }
            },

            setScaleResize: function (heightView, widthView) {
                heightView.setMode('edit');
                widthView.setMode('edit');

                if (!this.downloadModel.get("width")) {
                    this.downloadModel.set("width", this.model.get("width"));
                }
                if (!this.downloadModel.get("height")) {
                    this.downloadModel.set("height", this.model.get("height"));
                }
            },

            changeFormat: function () {
                let qualityView = this.getView("quality");
                if (this.downloadModel.get("format") === "png") {
                    this.downloadModel.set("quality", 100);
                    qualityView.setMode('detail');
                } else {
                    qualityView.setMode('edit');
                }

                qualityView.reRender();
            },

            buildUrl: function () {
                return `?entryPoint=download&id=${this.model.get('id')}` + "&" +
                    `width=${this.downloadModel.get("width") ?? ''}` + "&" +
                    `height=${this.downloadModel.get("height") ?? ''}` + "&" +
                    `quality=${this.downloadModel.get("quality") ?? ''}` + "&" +
                    `scale=${this.downloadModel.get("mode") ?? ''}` + "&" +
                    `format=${this.downloadModel.get("format") ?? ''}` + "&" +
                    `type=custom`;
            },

            actionDownload: function () {
                window.open(this.buildUrl(), '_blank');
            }
        });
    }
);