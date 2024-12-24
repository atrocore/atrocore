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

Espo.define('views/record/row-actions/default', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/row-actions/default',

        setup: function () {
            this.options.acl = this.options.acl || {};
        },

        afterRender: function () {
            var $dd = this.$el.find('button[data-toggle="dropdown"]').parent();

            var isChecked = false;
            $dd.on('show.bs.dropdown', function () {
                this.loadDynamicActions()

                var $el = this.$el.closest('.list-row');
                isChecked = false;
                if ($el.hasClass('active')) {
                    isChecked = true;
                }
                $el.addClass('active');
            }.bind(this));
            $dd.on('hide.bs.dropdown', function () {
                if (!isChecked) {
                    this.$el.closest('.list-row').removeClass('active');
                }
            }.bind(this));
        },

        isInheritingRelation: function () {
            if (
                this.getParentView()
                && this.getParentView().getParentView()
                && this.getParentView().getParentView().getParentView()
                && typeof this.getParentView().getParentView().getParentView().isInheritingRelation === 'function'
            ) {
                return this.getParentView().getParentView().getParentView().isInheritingRelation();
            }

            return false;
        },

        modelHasChildren: function () {

            if (
                this.getParentView()
                && this.getParentView().getParentView()
                && this.getParentView().getParentView().getParentView()
                && this.getParentView().getParentView().getParentView().getParentView()
                && this.getParentView().getParentView().getParentView().getParentView().model
            ) {
                return this.getParentView().getParentView().getParentView().getParentView().model.get("hasChildren");
            }

            return false;
        },

        loadDynamicActions: function () {
            if (!$(this.$el).find('li.divider').get(0)) {
                return
            }

            if (this.model.dynamicActions == null) {
                $(this.$el).find('li.preloader,li.divider').show()
                $(this.$el).find('li.dynamic-action').remove()
            }

            this.model.fetchDynamicActions()
                .then(dynamicActions => {
                    $(this.$el).find('li.dynamic-action').remove()

                    const template = this._templator.compileTemplate(`
                    {{#each dynamicActions}}
                        <li class="dynamic-action"><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}</a></li>
                    {{/each}}`)
                    const html = this._renderer.render(template, {dynamicActions})

                    $(this.$el).find('li.preloader').hide()
                    $(html).insertBefore($(this.$el).find('li.preloader'))
                    if (dynamicActions.length === 0) {
                        $(this.$el).find('li.divider').hide()
                    }
                })
        },

        getActionList: function () {
            const scope = this.options.scope;
            const filters = this.getStorage().get('listSearch', scope);
            if (filters && filters.bool['onlyDeleted'] === true) {
                if (this.options.acl.delete) {
                    return [
                        {
                            action: 'quickRestore',
                            label: 'Restore',
                            data: {
                                id: this.model.id
                            }
                        },
                        {
                            action: 'deletePermanently',
                            label: 'deletePermanently',
                            data: {
                                id: this.model.id
                            }
                        }
                    ];
                }
            }
            var list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                },
                link: '#' + this.model.name + '/view/' + this.model.id
            }];

            if (this.options.acl.edit) {
                list.push({
                    action: 'quickEdit',
                    label: 'Edit',
                    data: {
                        id: this.model.id
                    },
                    link: '#' + this.model.name + '/edit/' + this.model.id
                });
            }

            if (this.getMetadata().get(['clientDefs', this.model.name, 'showCompareAction'])) {
                list.push({
                    action: 'quickCompare',
                    label: this.translate('Instance comparison'),
                    name: 'compare',
                    data: {
                        id: this.model.id,
                        scope: this.model.name
                    },
                    link: '#' + this.model.name + '/compare?id=' + this.model.id
                });
            }

            if (this.options.acl.delete) {
                list.push({
                    action: 'quickRemove',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }

            list.push({
                divider: true
            });

            list.push({
                preloader: true
            });

            return list;
        },
        handleDataBeforeRender: function (data) {
            Dep.prototype.handleDataBeforeRender.call(data)
            data['actionList'] = this.getActionList();
        },
        data: function () {
            return {
                acl: this.options.acl,
                actionList: this.getActionList(),
                scope: this.model.name,
                hasInheritedIcon: this.model.has('isInherited'),
                isInherited: this.model.get('isInherited')
            };
        }
    });

});
