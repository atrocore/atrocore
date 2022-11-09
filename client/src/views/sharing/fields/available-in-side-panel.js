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

Espo.define('views/sharing/fields/available-in-side-panel', 'views/fields/bool', function (Dep) {

    return Dep.extend({

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            //
            // // let html = `<a target="_blank" href="${this.model.get(this.name)}" title="${this.model.get(this.name)}">link</a>`;
            //
            // let html = '';
            // // html += `<div style="width: 100%;float: left"><input type="checkbox" style="margin-right: 7px" checked disabled />`;
            // html += `<div style="width: 100%;float: left"><i style="margin-right: 7px; color: #0081d1" class="fas fa-check-circle"></i>`;
            // // html += `<div style="width: 100%;float: left"><i style="margin-right: 7px; color: #999" class="fas fa-minus-circle"></i>`;
            //
            // // <i className="fas fa-minus-circle"></i>
            // html += `<a target="_blank" href="${this.model.get(this.name)}" title="${this.model.get(this.name)}">${this.model.get('name')}</a>`;
            // // html += `<input type="url" style="width: 90%" disabled value="${this.model.get(this.name)}" />`;
            // html += `<i style="margin-left: 7px" class="far fa-copy"></i></div>`;
            // // html += `<div style="width: 100%;float: left;margin-top: 5px"><i class="far fa-user" style="color: #999"></i><a style="margin-left: 7px" href="33">Admin</a></div>`;
            //
            // this.$el.html(html)
        }

    });

});
