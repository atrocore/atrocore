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

Espo.define('views/admin/entity-manager/fields/child-behavior', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        setupOptions() {
            this.params.options = ["Do nothing"];
            
            if (this.model.get('multiParents') || !$('[name="multiParents"]:checked').length) {
                this.params.options.push("Retain all");
                this.params.options.push("Remove all");
            }
            this.translatedOptions = {
                "Do nothing": this.getLanguage().translateOption('Nothing', 'childBehavior', 'EntityManager'),
                "Retain all": this.getLanguage().translateOption('Retain', 'childBehavior', 'EntityManager'),
                "Remove all": this.getLanguage().translateOption('Remove', 'childBehavior', 'EntityManager'),
            };
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.checkFieldVisibility(this.model.get('childBehavior'));

            const $fieldValueInheritance = this.$el.parent().parent().parent().find('.field[data-name="fieldValueInheritance"] input');
            $fieldValueInheritance.change(() => {
                this.checkFieldVisibility($fieldValueInheritance.is(':checked'));
            });
            
            var rootElem = this;
            $('[name="multiParents"]').on('change', function () {
                rootElem.setupOptions();
                rootElem.reRender();
            });
        },
        
        checkFieldVisibility(fieldValueInheritance) {
            if (this.model.get('type') === 'Hierarchy') {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        }

    });
});