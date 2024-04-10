/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/modals/upload', 'views/modals/edit',
    Dep => Dep.extend({

        fullFormDisabled: true,

        events: _.extend(Dep.prototype.events, {
                'click #upload-via-url': function (e) {
                    if (!this.$el.find('#upload-via-url .btn-primary').hasClass('disabled')) {
                        this.model.trigger('click:upload-via-url');
                    }
                },
            },
        ),

        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Close'
                }
            ];

            this.header = this.translate('Upload', 'labels', 'File');

            this.listenTo(this.model, 'upload-disabled', disabled => {
                if (disabled) {
                    this.$el.find('#upload-via-url .btn-primary').addClass('disabled');
                    this.$el.find('#upload-url-input').attr('disabled', 'disabled');
                } else {
                    this.$el.find('#upload-via-url .btn-primary').removeClass('disabled');
                    this.$el.find('#upload-url-input').removeAttr('disabled');
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let html = '';
            html += `<div class="row"><div class="cell col-sm-12 form-group" data-name="upload-url"><label class="control-label" data-name="upload-url"><span class="label-text">URL</span></label><div class="field" data-name="upload-url"><div class="input-group"><input id="upload-url-input" type="text" placeholder="${this.translate('putUploadUrl', 'labels', 'File')}" class="main-element form-control upload-url" name="upload-url" value="" autoComplete="off"><span id="upload-via-url" class="input-group-btn"><span class="form-control btn btn-primary">${this.translate('Upload', 'labels', 'File')}</span></span></div></div></div><div class="col-sm-6"></div></div>`;
            html += '<div class="row"><div class="cell col-sm-12 form-group" data-name="files"><div class="field" data-name="files"></div></div><div class="col-sm-6"></div></div>';

            this.$el.find('.modal-content .edit-container .edit .middle .panel-body').append(html);

            this.createView('files', 'views/file/upload', {
                name: 'files',
                model: this.model,
                multiUpload: this.options.multiUpload ?? true,
                el: this.getSelector() + ' .field[data-name="files"]',
                mode: 'edit',
                attributes: this.attributes || {},
            }, view => {
                view.render();
            });
        }
    })
);