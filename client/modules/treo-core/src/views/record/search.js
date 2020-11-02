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

Espo.define('treo-core:views/record/search', 'class-replace!treo-core:views/record/search', function (Dep) {

    return Dep.extend({

        template: 'treo-core:record/search',

        typesWithOneFilter: ['array', 'bool', 'enum', 'multiEnum', 'linkMultiple'],

        hiddenBoolFilterList: [],

        boolFilterData: {},

        events: _.extend({}, Dep.prototype.events, {
            'click a[data-action="addFilter"]': function (e) {
                var $target = $(e.currentTarget);
                var name = $target.data('name');
                var nameCount = 1;
                var getLastIndexName = function () {
                    if (this.advanced.hasOwnProperty(name + '-' + nameCount)) {
                        nameCount++;
                        getLastIndexName.call(this);
                    }
                };
                getLastIndexName.call(this);
                name = name + '-' + nameCount;
                this.advanced[name] = {};
                this.advanced = this.sortAdvanced(this.advanced);

                var nameType = this.model.getFieldType(name.split('-')[0]);
                if (this.typesWithOneFilter.includes(nameType)) {
                    $target.closest('li').addClass('hide');
                }

                this.presetName = this.primary;

                this.createFilter(name, {}, function (view) {
                    view.populateDefaults();
                    this.fetch();
                    this.updateSearch();
                    this.setupOperatorLabels();
                }.bind(this));
                this.updateAddFilterButton();
                this.handleLeftDropdownVisibility();

                this.manageLabels();
            },
            'click .advanced-filters a.remove-filter': function (e) {
                var $target = $(e.currentTarget);
                var name = $target.data('name');

                this.$el.find('ul.filter-list li[data-name="' + name.split('-')[0] + '"]').removeClass('hide');
                var container = this.getView('filter-' + name).$el.closest('div.filter');
                this.clearView('filter-' + name);
                container.remove();
                delete this.advanced[name];
                this.presetName = this.primary;

                this.updateAddFilterButton();

                this.fetch();
                this.updateSearch();

                this.manageLabels();
                this.handleLeftDropdownVisibility();
                this.setupOperatorLabels();
            },
            'keypress .field input[type="text"]': function (e) {
                if (e.keyCode === 13) {
                    this.search();
                }
            },
            'click .dropdown-submenu > a.add-filter-button': function (e) {
                let a = $(e.currentTarget);
                a.parents('.dropdown-menu').find('> .dropdown-submenu > a:not(.add-filter-button)').next('ul').toggle(false);
                a.next('ul').toggle();
                e.stopPropagation();
                e.preventDefault();
            }
        }),

        data() {
            const data = Dep.prototype.data.call(this);

            data.boolFilterListLength = 0;
            data.boolFilterListComplex = data.boolFilterList.map(item => {
                let includes = this.hiddenBoolFilterList.includes(item);
                if (!includes) {
                    data.boolFilterListLength++;
                }
                return {name: item, hidden: includes};
            });

            return _.extend({
                isModalDialog: this.viewMode !== 'list'
            }, data);
        },

        setup: function () {
            this.hiddenBoolFilterList = this.options.hiddenBoolFilterList || this.hiddenBoolFilterList;
            this.boolFilterData = this.options.boolFilterData || this.boolFilterData;

            Dep.prototype.setup.call(this);
        },

        manageBoolFilters() {
            (this.boolFilterList || []).forEach(item => {
                if (this.bool[item] && !this.hiddenBoolFilterList.includes(item)) {
                    this.currentFilterLabelList.push(this.translate(item, 'boolFilters', this.entityType));
                }
            });
        },

        updateCollection() {
            this.collection.reset();
            this.notify('Please wait...');
            this.listenTo(this.collection, 'sync', function () {
                this.notify(false);
            }.bind(this));
            let where = this.searchManager.getWhere();
            where.forEach(item => {
                if (item.type === 'bool') {
                    let data = {};
                    item.value.forEach(elem => {
                        if (elem in this.boolFilterData) {
                            data[elem] = this.boolFilterData[elem];
                        }
                    });
                    _.extend(item.data, data);
                }
            });
            this.collection.where = where;
            this.collection.fetch();
        },

        sortAdvanced: function (advanced) {
            var result = {};
            Object.keys(advanced).sort(function (item1, item2) {
                return item1.localeCompare(item2, undefined, {numeric: true});
            }).forEach(function (item) {
                result[item] = advanced[item];
            }.bind(this));
            return result;
        },

        updateAddFilterButton: function () {
            var $ul = this.$el.find('ul.filter-list');
            if ($ul.children().not('.hide').size() == 0) {
                this.$el.find('a.add-filter-button').addClass('hidden');
            } else {
                this.$el.find('a.add-filter-button').removeClass('hidden');
            }
        },

        savePreset(name) {
            let id = 'f' + (Math.floor(Math.random() * 1000001)).toString();

            this.fetch();
            this.updateSearch();

            let presetFilters = this.getPreferences().get('presetFilters') || {};
            if (!(this.scope in presetFilters)) {
                presetFilters[this.scope] = [];
            }

            let data = {
                id: id,
                name: id,
                label: name,
                data: Espo.Utils.cloneDeep(this.advanced),
                primary: this.primary
            };

            presetFilters[this.scope].push(data);

            this.presetFilterList.push(data);

            this.getPreferences().once('sync', () => {
                this.getPreferences().trigger('update');
                this.updateSearch()
            });

            this.getPreferences().save({
                'presetFilters': presetFilters
            }, {patch: true});

            this.presetName = id;
        },

        afterRender: function () {
            this.$filtersLabel = this.$el.find('.search-row span.filters-label');
            this.$filtersButton = this.$el.find('.search-row button.filters-button');
            this.$leftDropdown = this.$el.find('div.search-row div.left-dropdown');

            this.updateAddFilterButton();

            this.$advancedFiltersBar = this.$el.find('.advanced-filters-bar');
            this.$advancedFiltersPanel = this.$el.find('.advanced-filters');

            this.manageLabels();
            this.setupOperatorLabels();
        },

        setupOperatorLabels() {
            let filters = this.$advancedFiltersPanel.find('.filter');

            let el = $(filters[0]);
            let curLabel = el.find('label.control-label');
            curLabel.text(this.getFilterName(el.data('name')));

            filters.each((index, filter) => {
                if (filters[index + 1]) {
                    let prevFilter = $(filter);
                    let prevName = prevFilter.data('name');
                    let nextFilter = $(filters[index + 1]);
                    let nextName = nextFilter.data('name');
                    let nextLabel = nextFilter.find('label.control-label');
                    if (prevName.split('-')[0] === nextName.split('-')[0]) {
                        nextLabel.text('OR ' + this.getFilterName(nextName));
                    } else {
                        nextLabel.text('AND ' + this.getFilterName(nextName));
                    }
                }
            });
        },

        getFilterName(filter) {
            let name = '';
            let nextView = this.getView('filter-' + filter);
            if (nextView) {
                if (nextView.options.params.isAttribute) {
                    name = nextView.options.params.label;
                } else {
                    name = this.translate(nextView.generalName, 'fields', this.scope);
                }
            }
            return name;
        },

        createFilter: function (name, params, callback, noRender) {
            params = params || {};

            var rendered = false;
            if (this.isRendered()) {
                rendered = true;
                var div = document.createElement('div');
                div.className = "filter filter-" + name;
                div.setAttribute("data-name", name);
                var nameIndex = name.split('-')[1];
                var beforeFilterName = name.split('-')[0] + '-' + (+nameIndex - 1);
                var beforeFilter = this.$advancedFiltersPanel.find('.filter.filter-' + beforeFilterName + '.col-sm-4.col-md-3')[0];
                var afterFilterName = name.split('-')[0] + '-' + (+nameIndex + 1);
                var afterFilter = this.$advancedFiltersPanel.find('.filter.filter-' + afterFilterName + '.col-sm-4.col-md-3')[0];
                if (beforeFilter) {
                    var nextFilter = beforeFilter.nextElementSibling;
                    if (nextFilter) {
                        this.$advancedFiltersPanel[0].insertBefore(div, beforeFilter.nextElementSibling);
                    } else {
                        this.$advancedFiltersPanel[0].appendChild(div);
                    }
                } else if (afterFilter) {
                    this.$advancedFiltersPanel[0].insertBefore(div, afterFilter);
                } else {
                    this.$advancedFiltersPanel[0].appendChild(div);
                }
            }

            this.createView('filter-' + name, 'treo-core:views/search/filter', {
                name: name,
                model: this.model,
                params: params,
                el: this.options.el + ' .filter[data-name="' + name + '"]'
            }, function (view) {
                if (typeof callback === 'function') {
                    view.once('after:render', function () {
                        callback(view);
                    });
                }
                if (rendered && !noRender) {
                    view.render();
                }
            }.bind(this));
        },

        getAdvancedDefs: function () {
            var defs = [];
            for (var i in this.moreFieldList) {
                var field = this.moreFieldList[i];
                var fieldType = this.model.getFieldType(field.split('-')[0]);
                var advancedFieldsList = [];
                Object.keys(this.advanced).forEach(function (item) {
                    advancedFieldsList.push(item.split('-')[0]);
                });
                var o = {
                    name: field,
                    checked: (this.typesWithOneFilter.indexOf(fieldType) > -1 && advancedFieldsList.indexOf(field) > -1),
                };
                defs.push(o);
            }
            return defs;
        },

        managePresetFilters: function () {
            var presetName = this.presetName || null;
            var data = this.getPresetData();
            var primary = this.primary;

            this.$el.find('ul.filter-menu a.preset span').remove();

            var filterLabel = this.translate('All');
            var filterStyle = 'default';

            if (!presetName && primary) {
                presetName = primary;
            }

            if (presetName && presetName != primary) {
                var label = null;
                var style = 'default';
                var id = null;

                this.presetFilterList.forEach(function (item) {
                    if (item.name == presetName) {
                        label = item.label || false;
                        style = item.style || 'default';
                        id = item.id;
                        return;
                    }
                }, this);
                label = label || this.translate(this.presetName, 'presetFilters', this.entityType);

                filterLabel = label;
                filterStyle = style;

                if (id) {
                    this.$el.find('ul.dropdown-menu > li.divider.preset-control').removeClass('hidden');
                    this.$el.find('ul.dropdown-menu > li.preset-control.remove-preset').removeClass('hidden');
                }

            } else {
                if (Object.keys(this.advanced).length !== 0) {
                    if (!this.disableSavePreset) {
                        this.$el.find('ul.dropdown-menu > li.divider.preset-control').removeClass('hidden');
                        this.$el.find('ul.dropdown-menu > li.preset-control.save-preset').removeClass('hidden');
                        this.$el.find('ul.dropdown-menu > li.preset-control.remove-preset').addClass('hidden');

                    }
                }

                if (primary) {
                    var label = this.translate(primary, 'presetFilters', this.entityType);
                    var style = this.getPrimaryFilterStyle();
                    filterLabel = label;
                    filterStyle = style;
                }
            }

            this.currentFilterLabelList.push(filterLabel);

            this.$filtersButton.removeClass('btn-default')
                .removeClass('btn-primary')
                .removeClass('btn-danger')
                .removeClass('btn-success')
                .removeClass('btn-info');
            this.$filtersButton.addClass('btn-' + filterStyle);

            presetName = presetName || '';

            this.$el.find('ul.filter-menu a.preset[data-name="'+presetName+'"]').prepend('<span class="fas fa-check pull-right"></span>');
        },

        isLeftDropdown() {
            return Dep.prototype.isLeftDropdown.call(this) || this.getAdvancedDefs().length;
        },
    });
});