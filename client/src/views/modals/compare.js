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

        setup: function () {
            this.model = this.options.model;
            this.scope = this.options.scope ?? this.model.urlRoot;
            this.className = this.options.className ?? this.className ;
            this.mode = this.options.mode ?? 'detail'
            this.instances = this.getMetadata().get(['app', 'comparableInstances']);
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.collection = this.options.collection ?? this.collection;

            Modal.prototype.setup.call(this)

            if (this.instanceComparison) {
                this.recordView = this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compareInstance']) ?? 'views/record/compare-instance'
               this.header = this.getLanguage().translate('Record Compare with') + ' ' + this.instances[0].name;
            } else {
                this.recordView = this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compare']) ?? this.recordView ?? 'view/record/compare'
                this.header = this.getLanguage().translate('Record Comparison');
            }

            this.setupRecord()
        },

        setupRecord() {
            this.notify('Loading...');
            this.wait(true);
            let options = {
                el: this.options.el + ' .modal-record',
                model: this.model,
                instanceComparison: this.instanceComparison,
                collection: this.options.collection,
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
                            if('_error' in attr){
                                if(attr._error.includes('404 Body')) {
                                    message = this.translate('recordDontExistInInstance', 'messages') + ' ' + this.instances[index].name;
                                }else if(attr._error.includes('401 Body')) {
                                    message = this.translate('badTokenInstance', 'messages') + ' ' + this.instances[index].name;
                                }else if(attr._error.includes('403 Body')) {
                                    message = this.translate('dontHaveAccessInInstance', 'messages') + ' ' + this.instances[index].name;
                                }else {
                                    message = this.translate('En error occur with the instance: ') + attr._error;
                                }
                                this.notify(message);
                                setTimeout(() => this.notify(false), 3000);
                                return;
                            }

                            for (let key in attr) {
                                let instanceUrl = this.instances[index].atrocoreUrl;
                                let value = attr[key];
                                if(key.includes('PathsData')){
                                    if( value && ('thumbnails' in value)){
                                        for (let size in value['thumbnails']){
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
                        this.wait(false);
                    });
                });
            } else {
                if (this.collection.models.length < 2) {
                    this.notify(this.translate('youShouldHaveAtLeastOneRecord'));
                    setTimeout(() => this.notify(false), 2000);
                    return;
                }else if (this.collection.models.length > 10){
                    let message = this.translate('weCannotCompareMoreThan');
                    this.notify(message.replace('%s', 10));
                    setTimeout(() => this.notify(false), 2000);
                    return;
                } else {
                    options.model = this.collection.models[0];
                    this.createModalView(options);
                }
                this.wait(false);
            }
        },

        createModalView(options) {
            this.createView('modalRecord', this.recordView, options);
        }
    });
});

