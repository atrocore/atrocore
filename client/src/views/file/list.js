/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/list', 'views/list',
    Dep => Dep.extend({

        createButton: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.menu.buttons.push({
                action: 'upload',
                label: this.translate('Upload', 'labels', 'File'),
                style: 'success',
                acl: 'create',
                aclScope: 'File'
            });
        },

        actionUpload() {
            this.notify('Loading...');
            this.createView('upload', 'views/file/modals/upload', {
                scope: 'File',
                attributes: _.extend({}, this.getCreateAttributes() || {}),
                fullFormDisabled: true,
                layoutName: 'upload'
            }, view => {
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    this.collection.fetch();
                    view.close();
                });
                view.listenTo(view.model, 'updating-started', () => view.disableButton('save'));
                view.listenTo(view.model, 'updating-ended', () => view.enableButton('save'));
                view.render();
            });
        },

    })
);

