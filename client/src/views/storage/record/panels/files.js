/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/storage/record/panels/files', 'views/record/panels/relationship', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.getAcl().check('File', 'delete')) {
                this.actionList.push({
                    label: 'unlinkAll',
                    action: 'unlinkAllFiles',
                    acl: 'delete',
                    aclScope: 'File'
                });
            }
        },

        actionUnlinkAllFiles() {
            this.confirm(this.translate('unlinkAllFilesConfirmation', 'messages', 'Storage'), () => {
                this.notify('Please wait...');
                $.ajax({
                    url: 'Storage/action/unlinkAllFiles',
                    type: 'POST',
                    data: JSON.stringify({
                        id: this.model.id
                    }),
                }).done(() => {
                    this.notify(false);
                    this.notify('Unlinked', 'success');
                    this.collection.fetch();
                    this.model.trigger('after:unrelate', this.link, this.defs);
                });
            });
        },

    });
});
