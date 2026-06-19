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

            if (this.getMetadata().get('app.typesWithWebPreview').includes(this.model.get('mimeType'))) {
                this.dropdownItemList.push({
                    'label': 'Open',
                    'name': 'openInTab'
                });
            }

            if (!this.model.get('originFileId')) {
                this.dropdownItemList.push({
                    'label': 'Reupload',
                    'name': 'reupload'
                });
            }

            this.additionalButtons.push({
                label: 'Download',
                name: 'download'
            });

            if (this.getMetadata().get('app.file.image.extensions').includes(this.model.get('extension'))) {
                this.dropdownItemList.push({
                    name: 'customDownload',
                    label: 'customDownload'
                });
            }

            if (!this.model.get('originFileId')) {
                const extension = (this.model.get('extension') || '').toLowerCase();
                const refData = this.getHelper().settings.get('referenceData') || {};
                const transformations = Object.values(refData.Transformation || {});
                const hasMatch = transformations.some(t =>
                    t.isActive && Array.isArray(t.inputFileExtensions) && t.inputFileExtensions.includes(extension)
                );
                if (hasMatch) {
                    this.additionalButtons.push({
                        name: 'createRenditionSystem',
                        label: this.translate('createRendition', 'labels', 'File'),
                        action: 'createRenditionSystem',
                    });
                }
            }
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
                attributes: _.extend(this.model.attributes, { reupload: this.model.id }),
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

        actionCustomDownload() {
            this.notify('Loading...');

            this.createView('customDownload', 'views/file/modals/custom-download', {
                fullHeight: false,
                model: this.model,
            }, view => {
                view.render();
                this.notify(false);
            });
        },

        actionDownload() {
            window.open(`/?entryPoint=download&id=${this.model.get('id')}`, "_blank");
        },

        actionCreateRenditionSystem() {
            const lastSelection = this.getStorage().get('renditions', 'lastSelection') || {};

            this.createView('createRenditionModal', 'renditions:views/file/modals/create-rendition', {
                el: 'body > .modal-container',
                model: this.model,
                transformationId: lastSelection.transformationId || null,
                lastParameters: lastSelection.parameters || {},
                fileExtension: (this.model.get('extension') || '').toLowerCase(),
                hideFileName: false,
                executeCallback: (data) => {
                    this.getStorage().set('renditions', 'lastSelection', {
                        transformationId: data.transformationId,
                        parameters: data.parameters || {},
                    });

                    return this.ajaxPostRequest('Rendition/createRendition', {
                        fileId: data.entityId,
                        transformationId: data.transformationId,
                        parameters: data.parameters || {},
                        fileName: data.fileName || '',
                        folderId: data.folderId || '',
                    }).then(response => {
                        this.notify(this.translate('renditionJobCreated', 'messages', 'File'), 'success');
                        if (response.link) {
                            window.open(response.link, '_blank');
                        }
                        const renditionsPanel = this.getView('renditions');
                        if (renditionsPanel) {
                            renditionsPanel.collection.fetch();
                        }
                    });
                }
            }, view => {
                view.render();
                this.listenToOnce(view, 'close', () => this.clearView('createRenditionModal'));
            });
        }

    })
);