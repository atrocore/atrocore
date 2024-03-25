/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/modals/select-records', 'views/modals/select-records',
    Dep => Dep.extend({

        events: {
            'click a[data-action="upload"]': function (e) {
                e.preventDefault();
                this.actionUpload();
            }
        },

        actionUpload() {
            this.notify('Loading...');
            this.createView('upload', 'views/file/modals/upload', {
                scope: 'File',
                fullFormDisabled: true,
                layoutName: 'upload',
                multiUpload: false,
                attributes: this.options.createAttributes || {},
            }, view => {
                view.once('after:render', () => {
                    this.notify(false);
                });
                view.render();

                view.listenTo(view.model, 'after:file-upload', entity => {
                    this.getModelFactory().create('File', model => {
                        model.set(entity);
                        this.trigger('select', model);
                    });
                });

                view.listenTo(view.model, 'after:delete-action', () => this.trigger('unselect'));
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.getAcl().check('File', 'create')) {
                this.$el.find('.modal-footer').append(`<div class="btn-group main-btn-group pull-right"><div class="page-header" style="margin-top: 0"><div class="header-buttons"><div class="header-items"><a href="javascript:" data-action="upload" class="btn action btn-success">${this.translate('Upload', 'labels', 'File')}</a></div></div></div></div>`);
            }
        },
    })
);