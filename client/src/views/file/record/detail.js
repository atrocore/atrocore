/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        duplicateAction: false,

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            if (this.getMetadata().get('app.file.image.hasPreviewExtensions').includes(this.model.get('extension'))) {
                this.dropdownItemList.push({
                    'label': 'Open',
                    'name': 'openInTab'
                });
            }

            this.dropdownItemList.push({
                'label': 'Reupload',
                'name': 'reupload'
            });
        },

        actionOpenInTab() {
            window.open(this.model.get('downloadUrl'), "_blank");
        },

        actionReupload() {
            this.notify('Loading...');
            this.createView('upload', 'views/file/modals/upload', {
                scope: 'File',
                fullFormDisabled: true,
                layoutName: 'upload',
                multiUpload: false,
                attributes: _.extend(this.model.attributes, {reupload: this.model.id}),
            }, view => {
                view.render();
                this.notify(false);

                this.listenTo(view.model, 'after:file-upload', entity => {
                    this.model.fetch();
                    this.model.trigger('reuploaded');
                });

                this.listenToOnce(view, 'close', () => {
                    this.clearView('upload');
                });
            });
        },

    })
);