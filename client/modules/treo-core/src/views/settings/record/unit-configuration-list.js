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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('treo-core:views/settings/record/unit-configuration-list', 'views/record/list',
    Dep => Dep.extend({

        actionQuickEditCustom(data) {
            data = data || {};
            let id = data.id;
            if (!id) return;

            let model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }
            if (!data.scope && !model) {
                return;
            }

            Espo.Ui.notify(this.translate('loading', 'messages'));
            this.createView('modal', 'treo-core:views/settings/modals/unit-edit', {
                model: model,
                id: id
            }, view => {
                view.once('after:render', function () {
                    Espo.Ui.notify(false);
                });

                this.listenToOnce(view, 'remove', () => {
                    this.clearView('modal');
                });

                this.listenToOnce(view, 'after:save', m => {
                    let model = this.collection.get(m.id);
                    if (model) {
                        model.set(m.getClonedAttributes());
                    }
                    this.trigger('update-configuration');
                });
                view.render();
            });
        },

    })
);

