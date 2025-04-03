/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/edit', 'views/main', function (Dep) {

    return Dep.extend({

        template: 'edit',

        scope: null,

        name: 'Edit',

        menu: null,

        optionsToPass: ['returnUrl', 'returnDispatchParams', 'attributes', 'rootUrl'],

        recordView: 'views/record/edit',

        rightSideView: 'views/record/right-side-view-edit',

        setup: function () {
            this.recordView = this.options.recordView || this.recordView;

            this.setupRecord();
        },

        setupHeader: function () {
            const record = this.getView('record');

            new Svelte.DetailHeader({
                target: document.querySelector('#main main > .header'),
                props: {
                    params: {
                        mode: 'edit',
                        scope: this.scope,
                        id: this.model.id,
                        permissions: {
                            canRead: this.getAcl().check(this.scope, 'read'),
                            canEdit: this.getAcl().check(this.scope, 'edit'),
                            canCreate: this.getAcl().check(this.scope, 'create'),
                            canDelete: this.getAcl().check(this.scope, 'delete'),
                            canReadStream: this.getAcl().check(this.scope, 'stream'),
                        },
                        breadcrumbs: this.getBreadcrumbsItems(),
                        afterOnMount: () => {
                            this.setupTourButton();
                        }
                    },
                    recordButtons: {
                        editButtons: record.buttonList,
                        dropdownEditButtons: record.dropdownEditItemList,
                        additionalEditButtons: record.additionalEditButtons,
                        headerButtons: this.getMenu(),
                        executeAction: (action, data, event) => {
                            this.executeAction(action, data, event);
                        },
                    }
                }
            });
        },

        getBreadcrumbsItems: function () {
            const items = Dep.prototype.getBreadcrumbsItems.call(this);

            const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
            items.push({
                label: this.getLanguage().translate(this.scope, 'scopeNamesPlural'),
                url: rootUrl,
            });

            if (this.model.isNew()) {
                items.push({
                    label: this.getLanguage().translate('New'),
                    url: `#${this.scope}/create`,
                });
            } else {
                items.push({
                    label: this.model.get('name') || this.model.id,
                    url: `#${this.scope}/view/${this.model.id}`,
                });
            }

            return items;
        },

        setupRecord: function () {
            var o = {
                model: this.model,
                el: '#main main > .record',
                scope: this.scope
            };
            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);
            if (this.options.params && this.options.params.rootUrl) {
                o.rootUrl = this.options.params.rootUrl;
            }
            this.createView('record', this.getRecordViewName(), o);
        },

        getRecordViewName: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.edit') || this.recordView;
        },

        updatePageTitle: function () {
            var title;
            if (this.model.isNew()) {
                title = this.getLanguage().translate('Create') + ' ' + this.getLanguage().translate(this.scope, 'scopeNames');
            } else {
                var name = this.model.get('name');
                if (name) {
                    title = name;
                } else {
                    title = this.getLanguage().translate(this.scope, 'scopeNames')
                }
            }
            this.setPageTitle(title);
        },

        afterRender() {
            $('.page-header').addClass('detail-page-header');

            Dep.prototype.afterRender.call(this);

            this.setupHeader();

            this.setupRightSideView();
        },

        canLoadActivities: function () {
            return false;
        }
    });
});


