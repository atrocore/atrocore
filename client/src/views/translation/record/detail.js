/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/translation/record/detail', 'views/record/detail', Dep => {

    return Dep.extend({

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            this.dropdownItemList.push({
                label: this.translate('reset', 'labels', 'Translation'),
                name: 'resetToDefault'
            });
        },

        actionResetToDefault() {
            this.confirm({
                message: this.translate('resetConfirm', 'messages', 'Translation'),
                confirmText: this.translate('Apply')
            }, () => {
                this.notify('Please wait...');

                if (this.model.get('isCustomized')) {
                    this.model.set('isCustomized', false);
                    this.model.save().then(() => {
                        this.ajaxPostRequest(`Translation/action/reset`).then(response => {
                            this.notify(this.translate('resetSuccessfully', 'messages', 'Translation'), 'success');
                            this.model.fetch();
                        });
                    });
                } else {
                    this.ajaxPostRequest(`Translation/action/reset`).then(response => {
                        this.notify(this.translate('resetSuccessfully', 'messages', 'Translation'), 'success');
                        this.model.fetch();
                    });
                }
            });
        },

    });
});

