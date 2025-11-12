/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/modals/compare', 'views/modal', function (Modal) {

    return Modal.extend({

        cssName: 'quick-compare',

        header: false,

        template: 'modals/compare',

        size: '',

        backdrop: true,

        recordView: 'views/record/compare',

        buttonList: [],

        buttons: [],

        instanceComparison: false,

        hideRelationship: false,

        className: 'full-page-modal',

        fullHeight: true,

        selectionId: null,

        setup: function () {
            this.model = this.options.model;
            this.scope = this.options.scope ?? this.model.urlRoot;
            this.className = this.options.className ?? this.className;
            this.mode = this.options.mode ?? 'detail'

            this.instances = this.getMetadata().get(['app', 'comparableInstances']);
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;

            this.versionComparison = this.options.versionComparison ?? false;
            this.versions = this.options.versions ?? [];
            this.currentVersion = this.versions[0]?.name

            this.collection = this.options.collection ?? this.collection;
            this.models = this.options.models || this.models;
            this.selectionId = this.options.selectionId || this.selectionId;

            Modal.prototype.setup.call(this)

            if (this.instanceComparison) {
                this.recordView = this.options.recordView ?? this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compareInstance']) ?? 'views/record/compare-instance'
                this.header = this.options.header ?? (this.getLanguage().translate('Record Compare with') + ' ' + this.instances[0].name);
            } else if (this.versionComparison) {
                this.recordView = this.options.recordView ?? this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compareVersion']) ?? 'views/record/compare-version'
                this.header = this.options.header ?? (this.getLanguage().translate('Record Compare with previous versions'));
            } else {
                this.recordView = this.options.recordView ?? this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compare']) ?? this.recordView ?? 'view/record/compare'
                this.header = this.options.header ?? (this.options.merging ? this.getLanguage().translate('Merge Records') : this.getLanguage().translate('Record Comparison'));
            }

            this.listenTo(this, 'after:render', () => this.setupRecord());

            this.buttonList = [
                {
                    name: 'merge',
                    style: 'primary',
                    label: 'Merge',
                    disabled: true,
                    onClick: (dialog) => {
                        this.trigger('merge', dialog)
                    }
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: (dialog) => {
                        this.trigger('cancel', dialog)
                    }
                }
            ];

            if (this.selectionId) {
                this.buttonList.push({
                    "name": "selectionView",
                    "label": "Selection View",
                    "disabled": true,
                    onClick: (dialog) => {
                        const link = '#Selection/view/' + this.selectionId +'/selectionViewMode=compare'  ;
                        this.getRouter().navigate(link, {trigger: false});
                        let options = {
                            id: this.selectionId,
                            selectionViewMode: this.getView('modalRecord').merging ? 'merge' : 'compare',
                            models: this.getModels()
                        }
                        dialog.close();
                        this.clearView('modalRecord');
                        this.getRouter().dispatch('Selection', 'view', options);
                    }
                })
            }
        },

        setupRecord() {
            this.notify('Loading...');
            let options = {
                el: this.options.el + ' .modal-record',
                model: this.model,
                instanceComparison: this.instanceComparison,
                collection: this.options.collection,
                models: this.options.models,
                selectionId: this.options.selectionId,
                scope: this.scope,
                merging: this.options.merging
            };

            if (this.instanceComparison) {
                this.getModelFactory().create(this.scope, scopeModel => {
                    this.ajaxPostRequest(`Synchronization/action/distantInstanceRequest`, {
                        uri: this.scope + '/' + this.model.id
                    }).success(attrs => {
                        options.distantModels = [];
                        for (const index in attrs) {
                            let attr = attrs[index];
                            if ('_error' in attr) {
                                if (attr._error.includes('404 Body')) {
                                    message = this.translate('recordDontExistInInstance', 'messages') + ' ' + this.instances[index].name;
                                } else if (attr._error.includes('401 Body')) {
                                    message = this.translate('badTokenInstance', 'messages') + ' ' + this.instances[index].name;
                                } else if (attr._error.includes('403 Body')) {
                                    message = this.translate('dontHaveAccessInInstance', 'messages') + ' ' + this.instances[index].name;
                                } else {
                                    message = this.translate('En error occur with the instance: ') + attr._error;
                                }
                                this.notify(message);
                                setTimeout(() => this.notify(false), 3000);
                                return;
                            }

                            for (let key in attr) {
                                let instanceUrl = this.instances[index].atrocoreUrl;
                                let value = attr[key];
                                if (key.includes('PathsData')) {
                                    if (value && ('thumbnails' in value)) {
                                        for (let size in value['thumbnails']) {
                                            attr[key]['thumbnails'][size] = instanceUrl + '/' + value['thumbnails'][size]
                                        }
                                    }
                                }
                            }

                            let distantModel = scopeModel.clone();
                            distantModel.set(attr);
                            distantModel.set('_instance', this.instances[index]);
                            options.distantModels.push(distantModel);
                        }
                        this.createModalView(options);
                    });
                });
            } else if (this.versionComparison) {
                this.getModelFactory().create(this.scope, scopeModel => {
                    this.ajaxGetRequest(`RecordVersion/action/getVersion`, {
                        scope: this.scope,
                        entityId: this.model.id,
                        name: this.currentVersion,
                    }).success(res => {
                        let versionModel = scopeModel.clone();
                        res['id'] = this.versions.find(v => v.name === this.currentVersion).id;
                        versionModel.set(res);
                        options.versionModel = versionModel;
                        options.versions = this.versions;
                        this.collection.reset();
                        this.collection.push(this.model);
                        this.collection.push(versionModel);
                        this.createModalView(options);
                    });
                });

                if (this.$el.find('.version-selector').length === 0) {
                    const container = $('<div class="version-selector" style="max-width: 400px; display: inline-block;margin-right: 20px"></div>');

                    container.insertAfter(this.$el.find('.modal-footer > .extra-content'))
                    this.getModelFactory().create(this.scope + 'Version', model => {
                        model.set('name', this.currentVersion)
                        this.createView('versionSelector', 'views/fields/enum', {
                            el: container.get(0),
                            name: 'name',
                            model: model,
                            scope: this.scope,
                            inlineEditDisabled: true,
                            mode: 'edit',
                            required: true,
                            params: {
                                options: this.versions.map(v => v.name),
                            }
                        }, (view) => {
                            view.render();
                            view.on('change', () => {
                                this.currentVersion = model.get('name')
                                const recordView = this.getView('modalRecord')
                                if (recordView) {
                                    recordView.remove()
                                }
                                this.setupRecord()
                            })
                        });
                    })
                }
            } else {
                if (this.getModels().length < 2) {
                    this.notify(this.translate('youShouldHaveAtLeastOneRecord'));
                    setTimeout(() => this.notify(false), 2000);
                } else if (this.getModels().length > 10) {
                    let message = this.translate('weCannotCompareMoreThan');
                    this.notify(message.replace('%s', 10));
                    setTimeout(() => this.notify(false), 2000);
                } else {
                    options.model = this.getModels()[0];
                    this.createModalView(options);
                    this.wait(false);

                }
            }
        },

        createModalView(options) {
            this.createView('modalRecord', this.recordView, options, (view) => {
                view.render();
                this.listenTo(view, 'merge-success', () => this.trigger('merge-success'));
                this.listenTo(this, 'merge', (dialog) => {
                    view.trigger('merge', dialog);
                });

                this.listenTo(this, 'cancel', (dialog) => {
                    view.trigger('cancel', dialog);
                });
            });
        },

        getModels() {
            return this.models ?? this.collection?.models ?? [];
        }
    });
});

