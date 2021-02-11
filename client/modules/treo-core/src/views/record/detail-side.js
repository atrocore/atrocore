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

Espo.define('treo-core:views/record/detail-side', 'class-replace!treo-core:views/record/detail-side', function (Dep) {

    return Dep.extend({

        streamPanel: true,

        defaultPanelDefs: {
            name: 'default',
            label: 'Ownership Information',
            view: 'views/record/panels/default-side',
            isForm: true,
            options: {
                fieldList: [
                    {
                        name: 'ownerUser',
                        view: 'views/fields/owner-user'
                    },
                    {
                        name: ':assignedUser'
                    },
                    {
                        name: 'teams'
                    }
                ]
            }
        },

        setupStreamPanel: function () {
            var streamAllowed = this.getAcl().checkModel(this.model, 'stream', true);
            if (streamAllowed === null) {
                this.listenToOnce(this.model, 'sync', function () {
                    streamAllowed = this.getAcl().checkModel(this.model, 'stream', true);
                    if (streamAllowed) {
                        this.showPanel('stream', function () {
                            this.getView('stream').collection.fetch();
                        });
                    }
                }, this);
            }
            if (streamAllowed !== false) {
                this.panelList.push({
                    "name": "stream",
                    "label": "Stream",
                    "view": "views/stream/panel",
                    "hidden": !streamAllowed
                });
            }
        },

        setup: function () {
            this.type = this.mode;
            if ('type' in this.options) {
                this.type = this.options.type;
            }

            if (this.defaultPanel) {
                this.setupDefaultPanel();
            }

            this.setupPanels();

            var additionalPanels = this.getMetadata().get('clientDefs.' + this.scope + '.sidePanels.' + this.type) || [];
            additionalPanels.forEach(function (panel) {
                this.panelList.push(panel);
            }, this);

            this.panelList = this.panelList.filter(function (p) {
                if (p.aclScope) {
                    if (!this.getAcl().checkScope(p.aclScope)) {
                        return;
                    }
                }
                if (p.accessDataList) {
                    if (!Espo.Utils.checkAccessDataList(p.accessDataList, this.getAcl(), this.getUser())) {
                        return false;
                    }
                }
                return true;
            }, this);

            this.panelList = this.panelList.map(function (p) {
                var item = Espo.Utils.clone(p);
                if (this.recordHelper.getPanelStateParam(p.name, 'hidden') !== null) {
                    item.hidden = this.recordHelper.getPanelStateParam(p.name, 'hidden');
                } else {
                    this.recordHelper.setPanelStateParam(p.name, item.hidden || false);
                }
                return item;
            }, this);

            this.wait(true);
            this.getHelper().layoutManager.get(this.scope, 'sidePanels' + Espo.Utils.upperCaseFirst(this.type), function (layoutData) {
                if (layoutData) {
                    this.alterPanels(layoutData);
                }

                if (this.streamPanel && this.getMetadata().get('scopes.' + this.scope + '.stream') && this.getConfig().get('isStreamSide') && !this.model.isNew()) {
                    this.setupStreamPanel();
                }

                this.setupPanelViews();
                this.wait(false);
            }.bind(this));
        },

        setupDefaultPanel() {
            this.defaultPanelDefs = Espo.Utils.cloneDeep(this.defaultPanelDefs);

            let scopeDefs = this.getMetadata().get(['scopes', this.scope]) || {};

            this.defaultPanelDefs.options.fieldList = this.defaultPanelDefs.options.fieldList.filter(fieldDefs => {
                return (scopeDefs.hasOwner && fieldDefs.name === 'ownerUser' && this.getAcl().check('User', 'read'))
                    || (scopeDefs.hasAssignedUser && fieldDefs.name === ':assignedUser' && this.getAcl().check('User', 'read'))
                    || (scopeDefs.hasTeam && fieldDefs.name === 'teams' && this.getAcl().check('Team', 'read'));
            });

            let hasAnyField = (this.defaultPanelDefs.options.fieldList || []).some(fieldDefs => {
                if (fieldDefs.name === ':assignedUser' && (this.model.hasField('assignedUsers') || this.model.hasField('assignedUser'))) {
                    return true;
                } else {
                    return this.model.hasLink(fieldDefs.name)
                }
            });
            if (this.mode === 'detail' || hasAnyField) {
                Dep.prototype.setupDefaultPanel.call(this);
            }
        }

    });
});

