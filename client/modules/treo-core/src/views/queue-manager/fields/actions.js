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

Espo.define('treo-core:views/queue-manager/fields/actions', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'treo-core:queue-manager/fields/actions/list',

        defaultActionDefs: {
            view: 'treo-core:views/queue-manager/actions/show-message'
        },

        data() {
            return {
                actions: this.model.get(this.name) || []
            };
        },

        afterRender() {
            this.buildActions();
        },

        buildActions() {
            (this.model.get(this.name) || []).forEach(action => {
                let actionDefs = this.getMetadata().get(['clientDefs', 'QueueItem', 'queueActions', action.type]) || this.defaultActionDefs;
                if (actionDefs.view && this.getAcl().check(this.model, actionDefs.acl)) {
                    this.createView(action.type, actionDefs.view, {
                        el: `${this.options.el} .queue-manager-action[data-type="${action.type}"]`,
                        actionData: action.data,
                        model: this.model
                    }, view => {
                        this.listenTo(view, 'reloadList', () => {
                            this.model.trigger('reloadList');
                        });
                        view.render();
                    });
                }
            });
        }

    })
);

