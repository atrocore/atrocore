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

Espo.define('views/admin/entity-manager/fields/un-inherited-relations', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            $.each((this.getMetadata().get(['entityDefs', this.model.get('name'), 'fields']) || {}), (field, fieldDefs) => {
                if (
                    fieldDefs.type === 'linkMultiple'
                    && !(this.getMetadata().get('app.nonInheritedRelations') || []).includes(field)
                    && !(this.getMetadata().get(['scopes', this.model.get('name'), 'mandatoryUnInheritedRelations']) || []).includes(field)
                ) {
                    let foreignEntity = this.getMetadata().get(['entityDefs', this.model.get('name'), 'links', field, 'entity']);
                    let isRelationshipEntity = this.getMetadata().get(['scopes', foreignEntity, 'type']) === 'Relationship';
                    if (isRelationshipEntity || this.getMetadata().get(['entityDefs', this.model.get('name'), 'links', field, 'relationName']) || (this.getMetadata().get(['scopes', this.model.get('name'), 'inheritedRelations']) || []).includes(field)) {
                        this.params.options.push(field);
                        this.translatedOptions[field] = this.translate(field, 'fields', this.model.get('name'));
                    }
                }
            });

            let newValue = [];
            (this.model.get(this.name) || []).forEach(field => {
                if (this.params.options.includes(field)) {
                    newValue.push(field);
                }
            });
            this.model.set(this.name, newValue);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.checkFieldVisibility(this.model.get('relationInheritance'));

            const $relationInheritance = this.$el.parent().parent().parent().find('.field[data-name="relationInheritance"] input');
            $relationInheritance.change(() => {
                this.checkFieldVisibility($relationInheritance.is(':checked'));
            });
        },

        checkFieldVisibility(relationInheritance) {
            if (this.model.get('type') === 'Hierarchy' && relationInheritance === true) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

    });
});