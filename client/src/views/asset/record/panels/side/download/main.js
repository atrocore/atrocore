/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/record/panels/side/download/main', 'view', Dep => {
        return Dep.extend({
            template: "asset/record/panels/side/download/main",
            active: "original",
            viewsLists: [
                "original",
                // "renditions",
                "custom"
            ],
            damConfig: null,

            events: {
                'change input[name="downloadType"]': function (e) {
                    let $el = $(e.currentTarget);
                    this._updateActive($el.val());
                },

                'click a[data-name="custom-download"]': function (e) {
                    let $el = $(e.currentTarget);
                    $el.prop("href", this._buildUrl());
                }
            },

            setup() {
                Dep.prototype.setup.call(this);
                // this.damConfig = Config.prototype.init.call(this);

                if (this.model.get("type")) {
                    this._buildViews();
                } else {
                    this.listenToOnce(this.model, "sync", () => {
                        if (this.model.get("type")) {
                            this._buildViews();
                            this.reRender();
                        }
                    });
                }
            },

            isImage() {
                const imageExtensions = this.getMetadata().get('dam.image.extensions') || [];
                const fileExt = (this.model.get('fileName') || '').split('.').pop().toLowerCase();

                return $.inArray(fileExt, imageExtensions) !== -1;
            },

            _buildUrl() {
                return this.getView(this.active).buildUrl();
            },

            _buildViews() {
                this._renderOriginal();
                if (this.isImage()) {
                    // this._renderRenditions();
                    this._renderCustom();
                }
            },

            _renderOriginal() {
                this.waitForView("original");
                this.createView("original", "views/asset/record/panels/side/download/original", {
                    el: this.options.el + ' div[data-name="original"]',
                    model: this.model
                });
            },

            _renderRenditions() {
                this.waitForView("renditions");
                this.createView("renditions", "views/asset/record/panels/side/download/renditions", {
                    el: this.options.el + ' div[data-name="renditions"]',
                    model: this.model
                });
            },

            _renderCustom() {
                this.waitForView("custom");
                this.createView("custom", "views/asset/record/panels/side/download/custom", {
                    el: this.options.el + ' div[data-name="custom"]',
                    model: this.model
                });
            },

            _updateActive(type) {
                for (let i in this.viewsLists) {
                    this.getView(this.viewsLists[i]).hide();
                }

                this.active = type;
                this.getView(type).show();
            }
        });
    }
);