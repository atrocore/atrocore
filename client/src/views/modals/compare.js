/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('views/modals/compare', ['views/modal','views/compare'], function (Modal, Compare) {

    return Modal.extend({

        cssName: 'quick-compare',

        header: false,

        template: 'modals/compare',

        size: '',

        backdrop: true,
        recordView: 'views/record/compare',
        buttonList:[],
        buttons:[],

        setup: function () {

            this.model = this.options.model;
            this.scope = this.model.urlRoot;
            this.header = this.getLanguage().translate('Compare')+' '+this.scope+' '+this.model.get('name')
            Modal.prototype.setup.call(this)
            this.buttonList.push({
                name: 'fullView',
                label: 'Full View'
            });
            this.setupRecord()

        },

        setupRecord() {
            this.notify('Loading...');
            this.ajaxGetRequest(`Connector/action/distantEntity?entityType=${this.scope}&id=${this.model.id}`, null, {async: false}).success(attr => {
                this.notify(false);
                var o = {
                    model: this.model,
                    distantModelAttribute: attr,
                    hideQuickMenu: true,
                    // el: '#main  .modal-record',
                    scope: this.scope
                };
                this.createView('modalRecord', this.recordView, o);
            })

        },
        actionFullView(data){
            if (!this.getAcl().check(this.scope, 'read')) {
                this.notify('Access denied', 'error');
                return false;
            }

            var url = '#' + this.scope + '/compare?id=' + this.model.get('id');
            this.getRouter().navigate(url, {trigger: false});
            this.getRouter().dispatch(this.scope, 'compare', {
                id: this.model.get('id'),
                model: this.model
            });
        }
    });
});

