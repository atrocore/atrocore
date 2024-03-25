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

                // this.listenToOnce(view, 'leave', () => {
                //     view.close();
                //     this.close();
                // });

                // this.listenToOnce(view, 'after:save', model => {
                //     view.close();
                //     this.trigger('select', model);
                //     setTimeout(() => {
                //         this.close();
                //     }, 10);
                // });
            });

            // this.notify('Loading...');
            // this.createView('upload', 'views/file/modals/upload', {
            //     scope: 'File',
            //     attributes: _.extend({}, this.getCreateAttributes() || {}),
            //     fullFormDisabled: true,
            //     layoutName: 'upload'
            // }, view => {
            //     view.notify(false);
            //     this.listenToOnce(view, 'after:save', () => {
            //         this.collection.fetch();
            //         view.close();
            //     });
            //     view.listenTo(view.model, 'updating-started', () => view.disableButton('save'));
            //     view.listenTo(view.model, 'updating-ended', () => view.enableButton('save'));
            //     view.listenTo(view.model, 'after:file-upload after:file-delete', () => this.collection.fetch());
            //     view.render();
            // });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.getAcl().check('File', 'create')) {
                this.$el.find('.modal-footer').append(`<div class="btn-group main-btn-group pull-right"><div class="page-header" style="margin-top: 0"><div class="header-buttons"><div class="header-items"><a href="javascript:" data-action="upload" class="btn action btn-success">${this.translate('Upload', 'labels', 'File')}</a></div></div></div></div>`);
            }
        },
    })
);