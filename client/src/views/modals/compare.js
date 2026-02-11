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

        selectionModel: null,

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

            this.derivativeComparison = this.options.derivativeComparison ?? false;

            this.collection = this.options.collection ?? this.collection;
            this.models = this.options.models || this.models;
            this.selectionModel = this.options.selectionModel || this.selectionModel;

            Modal.prototype.setup.call(this)

            if (this.instanceComparison) {
                this.recordView = this.options.recordView ?? this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compareInstance']) ?? 'views/record/compare-instance'
                this.header = this.options.header ?? (this.getLanguage().translate('Record Compare with') + ' ' + this.instances[0].name);
            } else if (this.versionComparison) {
                this.recordView = this.options.recordView ?? this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compareVersion']) ?? 'versioning:views/record/compare-version'
                this.header = this.options.header ?? (this.getLanguage().translate('Version comparison'));
            } else if (this.derivativeComparison) {
                this.recordView = this.options.recordView ?? this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compareVersion']) ?? 'versioning:views/record/compare-derivative'
                this.header = this.options.header ?? (this.getLanguage().translate('Change Request comparison'));
            } else {
                this.recordView = this.options.recordView ?? this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compare']) ?? this.recordView ?? 'view/record/compare'
                this.header = this.options.header ?? (this.options.merging ? this.getLanguage().translate('Merge Records') : this.getLanguage().translate('Record Comparison'));
            }

            this.listenTo(this, 'after:render', () => {
                this.$el.find('.modal-body.body').css('overflow-y', 'hidden');

                // move buttons to the right
                let $compareButtonContainer = $('#compare-button-container');
                if ($compareButtonContainer.length === 0) {
                    $compareButtonContainer = $('<div id="compare-button-container" style="display: inline-block"></div>');
                    this.$el.find('.modal-footer').append($compareButtonContainer);
                    this.$el.find('.modal-footer').find('[data-name="switchToCompare"]').appendTo($compareButtonContainer);
                    this.$el.find('.modal-footer').find('[data-name="switchToMerge"]').appendTo($compareButtonContainer);
                }

                this.setupRecord();
            });

            this.buttonList = [];

            if (this.getAcl().check(this.scope, 'create')) {
                this.buttonList.push({
                    name: 'merge',
                    style: 'primary',
                    label: 'Merge',
                    disabled: true,
                    hidden: !this.options.merging,
                    onClick: (dialog) => {
                        this.trigger('merge', dialog)
                    }
                });
            }

            if (!this.versionComparison && !this.options.disableSelection && this.getAcl().check('Selection', 'create') && this.getAcl().check('Selection', 'read')) {
                this.buttonList.push({
                    name: "createSelection",
                    label: this.translate('createSelection', 'labels', 'Selection'),
                    disabled: true,
                    onClick: (dialog) => {
                        this.ajaxPostRequest('selection/action/createSelectionWithRecords', {
                            scope: this.scope,
                            entityIds: this.getModels().map(m => m.id)
                        }).then(result => {
                            this.getModelFactory().create('Selection', (selectionModel) => {
                                selectionModel.set(result);
                                const link = '#Selection/view/' + result.id + '/selectionViewMode=' + (this.getView('modalRecord').merging ? 'merge' : 'compare');
                                this.getRouter().navigate(link, { trigger: false });
                                this.getRouter().dispatch('Selection', 'view', {
                                    id: result.id,
                                    model: selectionModel,
                                    selectionViewMode: this.getView('modalRecord').merging ? 'merge' : 'compare',
                                });
                                dialog.close();
                                this.clearView('modalRecord');
                            });
                        });
                    }
                })
            }

            (this.options.additionalButtons || []).forEach(button => this.buttonList.push(button))

            this.buttonList.push({
                name: 'close',
                label: 'Close',
                onClick: (dialog) => {
                    dialog.close();
                }
            });

            if (this.getAcl().check(this.scope, 'create')) {
                this.buttonList.push({
                    name: 'switchToCompare',
                    style: this.options.merging ? '' : 'primary',
                    label: '<i class="ph ph-arrows-left-right"></i>',
                    onClick: (dialog) => {
                        this.getView('modalRecord').trigger('switchToCompare', dialog)
                        $('#compare-button-container').find('[data-name="switchToCompare"]').addClass('btn-primary').siblings().removeClass('btn-primary');
                        this.$el.find('.modal-footer [data-name="merge"]').addClass('hidden')
                    }
                });

                this.buttonList.push({
                    name: 'switchToMerge',
                    style: this.options.merging ? 'primary' : '',
                    label: '<i class="ph ph-arrows-merge"></i>',
                    onClick: (dialog) => {
                        this.trigger('switchToMerge', dialog)
                        $('#compare-button-container').find('[data-name="switchToMerge"]').addClass('btn-primary').siblings().removeClass('btn-primary');
                        this.$el.find('.modal-footer [data-name="merge"]').removeClass('hidden')
                    }
                });
            }

        },

        getComparisonScope: function () {
            const scopeDefs = this.getMetadata().get(['scopes', this.scope]) || {};
            if (scopeDefs.primaryEntityId && scopeDefs.role === 'staging') {
                return scopeDefs.primaryEntityId;
            }

            return this.scope;
        },

        setupRecord() {
            this.notify('Loading...');
            let options = {
                el: this.options.el + ' .modal-record',
                model: this.model,
                instanceComparison: this.instanceComparison,
                collection: this.options.collection,
                models: this.options.models,
                selectionModel: this.options.selectionModel,
                scope: this.getComparisonScope(),
                merging: this.options.merging,
                mergeCallback: this.options.mergeCallback,
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
                    if (this.derivativeComparison) {
                        options.derivativeComparison = true;
                    }
                    this.createModalView(options);
                    this.wait(false);

                }
            }
        },

        createModalView(options) {
            this.createView('modalRecord', this.recordView, options, (view) => {
                this.listenTo(view, 'merge-success', () => this.trigger('merge-success'));

                this.listenTo(view, 'all-panels-rendered', () => {
                    this.$el.find('.modal-body.body').css('overflow-y', 'auto');
                    ['merge', 'createSelection'].forEach(action => {
                        $(`button[data-name="${action}"]`).removeClass('disabled');
                        $(`button[data-name="${action}"]`).attr('disabled', false);
                    });
                    $('.button-container a').removeClass('disabled');
                });

                this.listenTo(view, 'layout-refreshed', () => {
                    this.setupRecord();
                })

                view.render();

                this.listenTo(this, 'merge', (dialog) => {
                    view.trigger('merge', dialog);
                });

                this.listenTo(this, 'switchToMerge', (dialog) => {
                    view.trigger('switchToMerge', dialog);
                    if (!this.versionComparison && !this.options.disableSelection) {
                        this.ajaxPostRequest('selection/action/createSelectionWithRecords', {
                            scope: this.scope,
                            entityIds: this.getModels().map(m => m.id)
                        });
                    }
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

