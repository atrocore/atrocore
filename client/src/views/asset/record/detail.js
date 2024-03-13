/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/record/detail', 'views/record/detail-tree',
    Dep => Dep.extend({

        duplicateAction: false,

        sideView: "views/asset/record/detail-side",

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'before:save', attrs => {
                if (attrs) {
                    let name = attrs[this.name] || null;
                    let filename = attrs['fileName'] || this.model.get("fileName") || '';

                    if (name && filename && name !== filename) {
                        attrs[this.name] = filename;
                    }
                }
            });

            this.listenTo(this.model, "change:name", () => {
                const name = this.model.get('name');
                if (this.mode === 'edit' && name) {
                    const ext = (this.model.get('fileName') || '').split('.').pop();
                    if (!name.endsWith('.' + ext)) {
                        this.model.set('name', name + '.' + ext, {silent: true});
                        this.model.set('fileName', this.model.get('name'));
                    }
                }
            });

            this.listenTo(this.model, "change:fileId", () => {
                this.toggleVisibilityForImagesAttributesFields();
            });
        },

        setupActionItems: function () {
            Dep.prototype.setupActionItems.call(this);

            if (this.model.get('hasOpen')) {
                this.dropdownItemList.push({
                    'label': 'Open',
                    'name': 'openInTab'
                });
            }
        },

        actionOpenInTab: function () {
            window.open(this.model.get('url'), "_blank");
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.toggleVisibilityForImagesAttributesFields();
        },

        toggleVisibilityForImagesAttributesFields() {
            ['width', 'height', 'orientation', 'colorDepth', 'colorSpace'].forEach(name => {
                if (this.isImage()) {
                    this.getView('middle').getView(name).show();
                } else {
                    this.getView('middle').getView(name).hide();
                }
            });
        },

        isImage() {
            const imageExtensions = this.getMetadata().get('dam.image.extensions') || [];
            const fileExt = (this.model.get('fileName') || '').split('.').pop().toLowerCase();

            return $.inArray(fileExt, imageExtensions) !== -1;
        },

    })
);