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

Espo.define('views/admin/layouts/side-panels-detail', 'views/admin/layouts/rows', function (Dep) {

    return Dep.extend({

        dataAttributeList: ['id', 'name', 'style', 'sticked'],

        dataAttributesDefs: {
            style: {
                type: 'enum',
                options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
                translation: 'LayoutManager.options.style'
            },
            sticked: {
                type: 'bool'
            },
            name: {
                readOnly: true
            }
        },

        editable: true,

        ignoreList: [],

        ignoreTypeList: [],

        viewType: 'detail',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.wait(true);
            this.loadLayout(function () {
                this.wait(false);
            }.bind(this));
        },

        loadLayout: function (callback) {
            this.getHelper().layoutManager.get(this.scope, this.type,this.layoutProfileId, function (layout) {
                this.readDataFromLayout(layout);
                if (callback) {
                    callback();
                }
            }.bind(this), false);
        },

        readDataFromLayout: function (layout) {
            var panelListAll = [];
            var labels = {};
            var params = {};

            if (
                this.getMetadata().get(['clientDefs', this.scope, 'defaultSidePanel', this.viewType]) !== false
                &&
                !this.getMetadata().get(['clientDefs', this.scope, 'defaultSidePanelDisabled'])
            ) {
                panelListAll.push('default');
                labels['default'] = 'Default';
            }
            (this.getMetadata().get(['clientDefs', this.scope, 'sidePanels', this.viewType]) || []).forEach(function (item) {
                if (!item.name) return;
                panelListAll.push(item.name);
                if (item.label) {
                    labels[item.name] = item.label;
                }
                params[item.name] = item;
            }, this);

            this.disabledFields = [];

            layout = layout || {};

            this.rowLayout = [];

            panelListAll.forEach(function (item, index) {
                var disabled = false;
                var itemData = layout[item] || {};
                if (itemData.disabled) {
                    disabled = true;
                }
                var labelText;
                if (labels[item]) {
                    labelText = this.getLanguage().translate(labels[item], 'labels', this.scope);
                } else {
                    labelText = this.getLanguage().translate(item, 'panels', this.scope);
                }

                if (disabled) {
                    this.disabledFields.push({
                        name: item,
                        label: labelText
                    });
                } else {
                    var o = {
                        name: item,
                        label: labelText
                    };
                    if (o.name in params) {
                        this.dataAttributeList.forEach(function (attribute) {
                            if (attribute === 'name') return;
                            var itemParams = params[o.name] || {};
                            if (attribute in itemParams) {
                                o[attribute] = itemParams[attribute];
                            }
                        }, this);
                    }
                    for (var i in itemData) {
                        o[i] = itemData[i];
                    }
                    o.index = ('index' in itemData) ? itemData.index : index;
                    this.rowLayout.push(o);
                }
            }, this);
            this.rowLayout.sort(function (v1, v2) {
                return v1.index - v2.index;
            });
        },

        fetch: function () {
            var layout = {};
            $("#layout ul.disabled > li").each(function (i, el) {
                var name = $(el).attr('data-name');
                layout[name] = {
                    disabled: true,
                    id: $(el).attr('data-id')
                };
            }.bind(this));

            $("#layout ul.enabled > li").each(function (i, el) {
                var $el = $(el);
                var o = {};
                var name = $el.attr('data-name');

                this.dataAttributeList.forEach(function (attribute) {
                    if (attribute === 'name') return;

                    var value = $el.data(Espo.Utils.toDom(attribute)) || null;
                    o[attribute] = value;
                });
                o.index = i;

                layout[name] = o;
            }.bind(this))

            return layout;
        },

    });
});


