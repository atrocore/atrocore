

/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://treolabs.com
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

Espo.define('treo-core:views/record/detail-small', 'class-replace!treo-core:views/record/detail-small',
    Dep => Dep.extend({

        template: 'treo-core:record/detail-small',

        isWide: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.isWide = this.isWide || this.sideDisabled;
        },

        prepareLayoutAfterConverting(layout) {
            layout = Dep.prototype.prepareLayoutAfterConverting.call(this, layout);

            (layout || []).forEach(panel => {
                (panel.rows || []).forEach(row => {
                    if (row[0]) {
                        if (!row[1]) {
                            row[0].fullWidth = true;
                        }
                    }
                });
            });

            return layout;
        },

        createSideView() {
            this.wait(true);
            let el = this.options.el || '#' + (this.id);
            this.createView('side', this.sideView, {
                model: this.model,
                scope: this.scope,
                el: el + ' .side',
                type: this.type,
                readOnly: this.readOnly,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this
            }, view => {
                if (!view.panelList.length) {
                    this.isWide = true;
                }
                this.wait(false);
            });
        },

    })
);