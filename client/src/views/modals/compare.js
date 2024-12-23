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
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.collection = this.options.collection ?? this.collection;
            this.hideRelationship = this.options.hideRelationship ?? this.hideRelationship;

            Modal.prototype.setup.call(this)


            if (this.instanceComparison) {
                this.recordView = this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compareInstance']) ?? 'views/record/compare-instance'
               this.header = this.getLanguage().translate('Instance Comparison');
            } else {
                this.recordView = this.getMetadata().get(['clientDefs', this.scope, 'recordViews', 'compare']) ?? this.recordView ?? 'view/record/compare'
                this.header = this.getLanguage().translate('Record Comparison');
            }

            this.listenTo(this, 'after:render', () => this.setupRecord())
        },

        setupRecord() {
            this.notify('Loading...');
            let options = {
                el: this.options.el + ' .modal-record',
                model: this.model,
                hideRelationship: this.hideRelationship,
                hideQuickMenu: true,
                instanceComparison: this.instanceComparison,
                collection: this.options.collection,
                scope: this.scope
            };

            if (this.instanceComparison) {
                this.ajaxPostRequest(`Synchronization/action/distantInstanceRequest`, {
                    uri: this.scope + '/' + this.model.id
                }).success(attr => {
                    options.distantModelsAttribute = attr;
                    this.createModalView(options)
                })
            } else {
                if (this.collection.models.length < 2) {
                    this.notify(this.translate('youShouldHaveAtLeastOneRecord'));
                    this.close();
                }else if (this.collection.models.length > 10){
                    let message = this.translate('weCannotCompareMoreThan');
                    this.notify(message.replace('%s', 10));
                    this.close()
                } else {
                    options.model = this.collection.models[0];
                    this.createModalView(options);
                }
            }
        },

        createModalView(options) {
            this.createView('modalRecord', this.recordView, options, view => {
                view.render()
                this.notify(false)
            });
        },

        actionFullView(data) {
            if (!this.getAcl().check(this.scope, 'read')) {
                this.notify('Access denied', 'error');
                return false;
            }

            const url = '#' + this.scope + '/compare?id=' + this.model.get('id');
            this.getRouter().navigate(url, {trigger: false});
            this.getRouter().dispatch(this.scope, 'compare', {
                id: this.model.get('id'),
                model: this.model
            });
            this.actionClose();
        }
    });
});

