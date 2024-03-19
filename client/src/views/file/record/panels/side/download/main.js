/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/panels/side/download/main', 'view', Dep => {
        return Dep.extend({
            template: "file/record/panels/side/download/main",
            active: "original",
            viewsLists: [
                "original",
                "custom"
            ],

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

                if (this.model.get("id")) {
                    this._buildViews();
                } else {
                    this.listenToOnce(this.model, "sync", () => {
                        if (this.model.get("id")) {
                            this._buildViews();
                            this.reRender();
                        }
                    });
                }
            },

            isImage() {
                return this.getMetadata().get('app.file.image.extensions').includes(this.model.get('extension'));
            },

            _buildUrl() {
                return this.getView(this.active).buildUrl();
            },

            _buildViews() {
                this._renderOriginal();
                if (this.isImage()) {
                    this._renderCustom();
                }
            },

            _renderOriginal() {
                this.waitForView("original");
                this.createView("original", "views/file/record/panels/side/download/original", {
                    el: this.options.el + ' div[data-name="original"]',
                    model: this.model
                });
            },

            _renderCustom() {
                this.waitForView("custom");
                this.createView("custom", "views/file/record/panels/side/download/custom", {
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