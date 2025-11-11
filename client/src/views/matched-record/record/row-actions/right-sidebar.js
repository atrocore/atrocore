/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matched-record/record/row-actions/right-sidebar', 'views/record/row-actions/default', Dep => {

    return Dep.extend({

        actionConfirm() {
            this.notify('Loading...');
            this.getModelFactory().create('MatchedRecord', model => {
                model.id = this.name;
                model.fetch().then(() => {
                    this.notify(false);
                    model.set('status', 'confirmed');
                    model.save().then(() => {
                        this.notify('Done', 'success');
                        this.trigger('after:save');
                    });
                });
            });
        },

        actionReject() {
            this.notify('Loading...');
            this.getModelFactory().create('MatchedRecord', model => {
                model.id = this.name;
                model.fetch().then(() => {
                    this.notify(false);
                    model.set('status', 'rejected');
                    model.save().then(() => {
                        this.notify('Done', 'success');
                        this.trigger('after:save');
                    });
                });
            });
        },

        actionEdit() {
            this.notify('Loading...');
            this.getModelFactory().create('MatchedRecord', model => {
                model.id = this.name;
                model.fetch().then(() => {
                    this.createView('modal', 'views/modals/edit', {
                        scope: model.name,
                        id: model.id,
                        model: model
                    }, view => {
                        this.notify(false);
                        view.render();
                        this.listenToOnce(view, 'after:save', m => {
                            this.trigger('after:save')
                        });
                    });
                });
            });
        },

        getActionList() {
            let list = [];

            if (this.options.parentNode.getAcl().check('MatchedRecord', 'edit')) {
                if (this.options.status !== 'confirmed') {
                    list.push({
                        action: 'confirm',
                        label: this.translate('Confirm')
                    });
                }

                if (this.options.status !== 'rejected') {
                    list.push({
                        action: 'reject',
                        label: this.translate('Reject')
                    });
                }

                list.push({
                    action: 'edit',
                    label: this.translate('Edit')
                });
            }

            return list;
        },

    });
});