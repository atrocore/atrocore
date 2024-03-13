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

Espo.define('views/record/panels/sharing', 'views/record/panels/relationship', function (Dep) {

    return Dep.extend({

        name: 'sharing',

        template: 'record/panels/sharing',

        buttonList: [
            {
                action: 'createSharing',
                title: 'create',
                acl: 'create',
                aclScope: 'Sharing',
                html: '<span class="fas fa-plus"></span>',
            }
        ],

        events: _.extend({
            'click .copy-link': function (e) {
                let link = $(e.currentTarget).data('link');

                let $temp = $("<input>");
                $("body").append($temp);
                $temp.val(link).select();
                document.execCommand("copy");
                $temp.remove();

                this.notify(this.translate('copiedToClipboard', 'labels', 'Sharing'), 'success');
            }
        }, Dep.prototype.events),

        setup: function () {
            this.scope = this.model.name;

            this.wait(true);
            this.getCollectionFactory().create('Sharing', function (collection) {
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;

                collection.where = [
                    {
                        "type": "equals",
                        "attribute": "entityType",
                        "value": this.scope
                    },
                    {
                        "type": "equals",
                        "attribute": "entityId",
                        "value": this.model.get('id')
                    }
                ];

                collection.sortBy = 'createdAt';
                collection.asc = true;

                this.collection = collection;

                let viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';

                this.once('after:render', function () {
                    this.createView('list', viewName, {
                        collection: collection,
                        layoutName: 'listSmall',
                        listLayout: [
                            {
                                "name": "available",
                                "width": 5,
                                "view": "views/sharing/fields/available-in-side-panel"
                            },
                            {
                                "name": "name",
                                "link": true
                            },
                            {
                                "name": "link",
                                "width": 5,
                                "view": "views/sharing/fields/link-in-side-panel"
                            }
                        ],
                        checkboxes: false,
                        rowActionsView: "views/record/row-actions/relationship-no-unlink",
                        buttonsDisabled: true,
                        el: this.options.el + ' .list-container',
                        skipBuildRows: true,
                        listRowsOrderSaveUrl: this.listRowsOrderSaveUrl,
                        panelView: this,
                    }, function (view) {
                        view.getSelectAttributeList(selectAttributeList => {
                            if (selectAttributeList) {
                                collection.data.select = selectAttributeList.join(',');
                            }
                            collection.fetch();
                        });

                        view.listenTo(view, 'after:render', view => {
                            view.$el.removeClass('no-data');
                            if (view.$el.find('table').length === 0) {
                                view.$el.addClass('no-data');
                            }
                        });
                    });
                }, this);

                this.wait(false);
            }, this);
        },

        actionCreateSharing: function (data) {
            let viewName = this.getMetadata().get('clientDefs.Sharing.modalViews.edit') || 'views/modals/edit';

            this.notify('Loading...');
            this.createView('quickCreate', viewName, {
                scope: 'Sharing',
                attributes: {
                    entityType: this.scope,
                    entityId: this.model.get('id'),
                    type: "download"
                },
            }, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.collection.fetch();
                    this.model.trigger('after:relate');
                }, this);
            });
        },

        actionRefresh: function () {
            this.collection.fetch();
        },

    });
});

