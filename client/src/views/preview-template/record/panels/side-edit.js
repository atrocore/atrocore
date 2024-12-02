/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/preview-template/record/panels/side-edit', 'view', function (Dep) {

    return Dep.extend({

        template: 'preview-template/panels/side-edit',

        name: 'sideEdit',

        scope: null,

        id: null,

        buttonList: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        events: {
            'click [data-action=save]': function(e) {
                this.actionSave();
            },
            'click [data-action=cancel]': function(e) {
                this.actionCancel();
            }
        },

        data() {
            return {
                buttonList: this.buttonList
            }
        },

        setup() {
            this.scope = this.options.scope || null;
            this.id = this.options.id || null;

            Dep.prototype.setup.call(this);
        },

        actionSave() {
            const edit = this.getView('edit');
            if (edit) {
                edit.save();
            }
        },

        actionCancel() {
            const edit = this.getView('edit');
            if (edit) {
                edit.remove();
            }

            this.trigger('cancel');
        },

        afterRender() {
            if (!this.scope || !this.id) {
                return;
            }

            const buttons = document.querySelectorAll('.html-preview .side-container .btn-group > button');

            this.getModelFactory().create(this.scope, model => {
                model.id = this.id;
                buttons.forEach(btn => btn.disabled = true);
                Espo.ui.notify('Loading...');

                model.fetch().success(() => {
                    Espo.ui.notify(false);
                    this.createRecordView(model, view => {
                        view.render();

                        buttons.forEach(btn => btn.disabled = false);

                        this.listenToOnce(view, 'remove', () => {
                            this.clearView('edit');
                        }, this);

                        this.listenToOnce(view, 'after:save', () => {
                            this.trigger('record:after:save');
                        }, this);
                    });
                }).error(() => {
                    Espo.ui.error('Failed to load selected record');
                    this.trigger('cancel');
                });
            });
        },

        createRecordView(model, callback) {
            const viewName =
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editSmall']) ||
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editQuick']) ||
                'views/record/edit-small';
            const options = {
                model: model,
                el: this.options.el + ' .side-body',
                type: 'editSmall',
                layoutName: 'detailSmall',
                columnCount: 1,
                buttonsDisabled: true,
                sideDisabled: true,
                bottomDisabled: false,
                exit: function () {
                }
            };

            this.createView('edit', viewName, options, callback);
        },
    });
});


