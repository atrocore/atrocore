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

        autosave: true,

        autosaveTimeout: 2000,

        scope: null,

        id: null,

        timerHandle: null,

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
            'click [data-action=save]': function (e) {
                this.actionSave();
            },
            'click [data-action=cancel]': function (e) {
                this.actionCancel();
            },
            'change [data-name=autosave]': function (e) {
                this.autosave = e.currentTarget?.checked;

                if (!this.autosave) {
                    clearTimeout(this.timerHandle);
                }

                this.trigger('autosaveChanged', this.autosave);
            },
            'keyup .field[data-name] > textarea[name]': function(e) {
                this.fieldKeyupCallback(e);
            },
            'keyup .field[data-name] > input[name]': function(e) {
                this.fieldKeyupCallback(e)
            }
        },

        fieldKeyupCallback: function (e) {
            const editView = this.getView('edit');
            if (editView) {
                const fieldView = editView.getField(e.target.name)
                fieldView?.fetchToModel();
                this.trigger('text-interact', e.target);
            }
        },

        data() {
            return {
                buttonList: this.buttonList,
                autosave: this.autosave
            }
        },

        setup() {
            this.scope = this.options.scope || null;
            this.id = this.options.id || null;

            if ('autosaveDisabled' in this.options) {
                this.autosave = !this.options.autosaveDisabled;
            }

            if (Number.isInteger(this.options.autosaveTimeout ?? null)) {
                this.autosaveTimeout = this.options.autosaveTimeout;
            }

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

        setButtonDisabledState(name, value) {
            const panel = this.$el.get(0);
            const button = panel?.querySelector(`[data-action=${name}]`);
            if (button) {
                button.disabled = !!value;
            }
        },

        isAutosaveEnabled() {
            return this.autosave;
        },

        afterRender() {
            if (!this.scope || !this.id) {
                return;
            }

            this.getModelFactory().create(this.scope, model => {
                model.id = this.id;
                Espo.ui.notify('Loading...');

                this.setButtonDisabledState('save', true);

                model.fetch().success(() => {
                    Espo.ui.notify(false);

                    this.createRecordView(model, view => {
                        view.render();

                        this.listenToOnce(view, 'remove', () => {
                            this.clearView('edit');
                            clearTimeout(this.timerHandle);
                        }, this);

                        this.listenTo(view, 'after:save', () => {
                            clearTimeout(this.timerHandle);
                            this.setButtonDisabledState('cancel', false);
                            this.trigger('record:after:save');
                        }, this);

                        this.listenTo(model, 'change', () => {
                            clearTimeout(this.timerHandle);
                            if (!view.hasChangedAttributes()) {
                                this.setButtonDisabledState('save', true);
                                return;
                            }

                            this.setButtonDisabledState('save', this.isAutosaveEnabled());

                            this.timerHandle = this.getAutosaveTimeoutHandle(view);
                        });

                        this.listenTo(this, 'text-interact', (element) => {
                            clearTimeout(this.timerHandle);
                            if (!view.hasChangedAttributes()) {
                                this.setButtonDisabledState('save', true);
                                return;
                            }

                            this.setButtonDisabledState('save', this.isAutosaveEnabled());
                            this.timerHandle = this.getAutosaveTimeoutHandle(view);
                        }, this);
                    });
                }).error(() => {
                    Espo.ui.error('Failed to load selected record');
                    this.trigger('cancel');
                });
            });
        },

        getAutosaveTimeoutHandle(view) {
            if (this.isAutosaveEnabled()) {
                return setTimeout(() => {
                    if (view.hasChangedAttributes()) {
                        view.save();
                        this.setButtonDisabledState('save', true);
                        this.setButtonDisabledState('cancel', true);
                    }
                }, this.autosaveTimeout);
            }

            return null;
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


