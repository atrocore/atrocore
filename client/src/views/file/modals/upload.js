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

        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList = [];
            this.header = this.translate('Upload', 'labels', 'File');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.find('.modal-content .edit-container .edit .middle .panel-body').append('<div class="row"><div class="cell col-sm-12 form-group" data-name="files"><div class="field" data-name="files"></div></div><div class="col-sm-6"></div></div>');

            this.createView('files', 'views/file/upload', {
                name: 'files',
                model: this.model,
                multiUpload: this.options.multiUpload ?? true,
                el: this.getSelector() + ' .field[data-name="files"]',
                mode: 'edit',
            }, view => {
                view.render();
            });
        }
    })
);