/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/compare-instance', ['views/main'], function (Dep) {

    return Dep.extend({

        template: 'compare',

        el: '#main',

        scope: null,

        name: 'Compare',

        headerView: 'views/header',

        recordView: 'views/record/compare-instance',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.model = this.options.model;
            this.scope = this.model.urlRoot;
            this.recordView = this.getMetadata().get('clientDefs.' + this.scope + '.compare.record') ?? 'views/record/compare-instance'
            this.updatePageTitle();
            this.setupHeader();
            this.setupRecord()
        },

        setupHeader: function () {
            this.createView('header', this.headerView, {
                model: this.model,
                el: '#main main > .page-header'
            });
        },

        getHeader: function () {

            var headerIconHtml = this.getHeaderIconHtml();

            var arr = [];

            if (this.options.noHeaderLinks) {
                arr.push(this.getLanguage().translate(this.scope, 'scopeNamesPlural'));
            } else {
                var rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                arr.push(headerIconHtml + '<a href="' + rootUrl + '" class="action" data-action="navigateToRoot">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>');
            }

            var name = Handlebars.Utils.escapeExpression(this.model.get('name'));

            if (name === '') {
                name = this.model.id;
            }

            if (this.options.noHeaderLinks) {
                arr.push(name);
            } else {
                arr.push('<a href="#' + this.scope + '/view/' + this.model.id + '" class="action">' + name + '</a>');
            }
            arr.push(this.getLanguage().translate('Instance comparison'));

            return this.buildHeaderHtml(arr);
        },

        setupRecord(name = 'instanceComparison') {
            this.notify('Loading...');
            let instances = this.getMetadata().get(['app', 'comparableInstances']);
            this.ajaxGetRequest(`${this.scope}/${this.model.id}/fromRemoteAtroCore`).success(attrs => {
                this.notify(false);
                this.getModelFactory().create(this.scope, scopeModel => {
                    let distantModels = [];
                    for (const index in attrs) {
                        let attr = attrs[index];
                        if (attr.error) {
                            let message;
                            if (attr.error.includes('404 Body')) {
                                message = this.translate('recordDontExistInInstance', 'messages');
                            } else if (attr.error.includes('401 Body')) {
                                message = this.translate('badTokenInstance', 'messages');
                            } else if (attr.error.includes('403 Body')) {
                                message = this.translate('dontHaveAccessInInstance', 'messages');
                            } else {
                                message = this.translate('En error occur with the instance: ') + attr.error;
                            }
                            instances[index]._error = message;
                            let distantModel = scopeModel.clone();
                            distantModel.set('_instance', instances[index]);
                            distantModels.push(distantModel);
                            continue;
                        }
                        let distantModel = scopeModel.clone();
                        distantModel.set(attr.data);
                        distantModel.set('_instance', instances[index]);
                        distantModels.push(distantModel);
                    }
                    var o = {
                        model: this.model,
                        distantModels: distantModels,
                        instances: instances,
                        instanceComparison: true,
                        el: '#main main > .' + name,
                        scope: this.scope
                    };
                    this.createView(name, this.recordView, o, view => view.render());
                });
            });
        },

        getMenu() {
            return {
                "buttons": []
            }
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Compare') + ' ' + this.scope + ' ' + this.model.get('name'));
        },

    });
});

