/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/software-package/modals/edit', 'views/modals/edit', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            const saveIndex = this.buttonList.findIndex(button => button.name === 'save');
            if (saveIndex === -1) {
                return;
            }

            // the button is revealed in controlActionButtons() once the record is loaded and the permission is known
            this.buttonList.splice(saveIndex + 1, 0, {
                name: 'saveAndUpdate',
                label: 'saveAndUpdate',
                hidden: true
            });
        },

        controlActionButtons() {
            Dep.prototype.controlActionButtons.call(this);

            if (this.model.get('_meta')?.permissions?.updatePackage) {
                this.showButton('saveAndUpdate');
            } else {
                this.hideButton('saveAndUpdate');
            }
        },

        actionSaveAndUpdate() {
            const recordView = this.getRecordView();
            if (!recordView) {
                return;
            }

            const id = this.model.id;
            const $buttons = this.dialog.$el.find('.modal-footer button');
            const unlockButtons = () => $buttons.removeClass('disabled').removeAttr('disabled');

            $buttons.addClass('disabled').attr('disabled', 'disabled');

            recordView.once('cancel:save', unlockButtons);

            recordView.once('after:save', () => {
                this.trigger('after:save', this.model);

                this.notify('Loading...');
                this.ajaxPostRequest(`SoftwarePackage/${id}/updatePackage`).success(() => {
                    this.notify(false);
                    location.reload();
                }).fail(() => {
                    this.notify(false);
                    unlockButtons();
                });
            }, this);

            recordView.save();
        },
    });
});
