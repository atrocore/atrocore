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
                el: '#main > main > .page-header'
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
            this.ajaxPostRequest(`Synchronization/action/distantInstanceRequest`, {
                uri: this.scope + '/' + this.model.id, type: 'one'
            }).success(attr => {
                this.notify(false);
                var o = {
                    model: this.model,
                    distantModelsAttribute: attr,
                    instanceComparison: true,
                    el: '#main > main > .' + name,
                    scope: this.scope
                };
                this.createView(name, this.recordView, o, view => view.render());
            })
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

