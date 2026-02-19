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

Espo.define('views/fields/link-multiple', ['views/fields/base', 'views/fields/colored-enum'], function (Dep, ColoredEnum) {

    return Dep.extend({

        type: 'linkMultiple',

        listTemplate: 'fields/link-multiple/list',

        detailTemplate: 'fields/link-multiple/detail',

        editTemplate: 'fields/link-multiple/edit',

        searchTemplate: 'fields/link-multiple/search',

        nameHashName: null,

        idsName: null,

        nameHash: null,

        foreignScope: null,

        AUTOCOMPLETE_RESULT_MAX_COUNT: 7,

        autocompleteDisabled: false,

        selectRecordsView: 'views/modals/select-records',

        createDisabled: false,

        uploadDisabled: true,

        sortable: false,

        linkMultiple: true,

        searchTypeList: ['anyOf', 'isEmpty', 'isNotEmpty', 'noneOf'],

        selectBoolFilterList: [],

        boolFilterData: {},

        noCreateScopeList: ['User', 'Team', 'Role'],

        sortBy: null,

        sortAsc: null,

        events: _.extend({
            'click [data-action="loadData"]': function (e) {
                this.actionLoadData();
            },
        }, Dep.prototype.events),

        getForeignName() {
            return this.getMetadata().get(['entityDefs', this.model.name, 'links', this.name, 'foreignName']) ?? this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'foreignName']) ?? 'name'
        },

        actionLoadData() {
            this.notify('Please wait...');

            let foreignName = this.getForeignName()

            this.ajaxGetRequest(`${this.model.name}/${this.model.get('id')}/${this.name}`, {
                select: `id, ${foreignName}`
            }).success(res => {
                let ids = [];
                let names = {};
                if (res.list) {
                    res.list.forEach(item => {
                        ids.push(item.id);
                        names[item.id] = item[foreignName];
                    })
                }

                this.model.set(this.nameHashName, names);
                this.model.set(this.idsName, ids);

                this.readOnly = false;
                this.reRender();

                this.notify('Done', 'success');
            }).error(() => {
                this.notify('Error occurred', 'error');
            });
        },

        getBoolFilterData() {
            let data = {};
            this.selectBoolFilterList.forEach(item => {
                if (typeof this.boolFilterData[item] === 'function') {
                    data[item] = this.boolFilterData[item].call(this);
                }
            });
            return data;
        },

        data: function () {
            let ids = this.model.get(this.idsName);
            let nameHash = this.model.get(this.nameHashName);

            let res = _.extend({
                idValues: this.model.get(this.idsName),
                idValuesString: ids ? ids.join(',') : '',
                nameHash: nameHash,
                foreignScope: this.foreignScope,
                placeholder: this.options.placeholder || this.translate('Select'),
                valueIsSet: this.model.has(this.idsName),
                createDisabled: this.createDisabled,
                uploadDisabled: this.uploadDisabled,
                hideSearchType: this.options.hideSearchType
            }, Dep.prototype.data.call(this));

            res.isNull = ids === null || ids === undefined;

            return res;
        },

        getSelectFilters: function () {
        },

        getSelectBoolFilterList: function () {
            return this.selectBoolFilterList;
        },

        getSelectPrimaryFilterName: function () {
            return this.selectPrimaryFilterName;
        },

        getCreateAttributes: function () {
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.nameHashName === null) {
                this.nameHashName = this.name + 'Names';
            }
            if (this.idsName === null) {
                this.idsName = this.name + 'Ids';
            }

            this.foreignScope = this.options.foreignScope || this.foreignScope || this.model.getFieldParam(this.name, 'entity') || this.model.getLinkParam(this.name, 'entity');

            if ('createDisabled' in this.options) {
                this.createDisabled = this.options.createDisabled;
            }

            if (!this.createDisabled && this.noCreateScopeList.indexOf(this.foreignScope) !== -1) {
                this.createDisabled = true;
            }

            if (!this.createDisabled) {
                if (
                    !this.getAcl().check(this.foreignScope, 'create')
                    ||
                    this.getMetadata().get(['clientDefs', this.foreignScope, 'createDisabled'])
                ) {
                    this.createDisabled = true;
                }
            }

            if (this.foreignScope === 'File') {
                this.uploadDisabled = false;
                this.createDisabled = true;
                if ('uploadDisabled' in this.options) {
                    this.uploadDisabled = this.options.uploadDisabled;
                }
                if (!this.uploadDisabled) {
                    if (!this.getAcl().check(this.foreignScope, 'create')) {
                        this.uploadDisabled = true;
                    }
                }
            }

            var self = this;

            this.ids = Espo.Utils.clone(this.model.get(this.idsName) || []);
            this.nameHash = Espo.Utils.clone(this.model.get(this.nameHashName) || {});

            if (this.mode == 'search') {
                this.nameHash = Espo.Utils.clone(this.searchParams.nameHash) || {};
                this.ids = Espo.Utils.clone(this.searchParams.value) || [];
            }
            this.nameHash._localeId = this.getUser().get('localeId')
            this.model.set(this.nameHashName, this.nameHash, { silent: true });

            this.listenTo(this.model, 'change:' + this.idsName, function () {
                this.ids = Espo.Utils.clone(this.model.get(this.idsName) || []);
                this.nameHash = Espo.Utils.clone(this.model.get(this.nameHashName) || {});
                this.nameHash._localeId = this.getUser().get('localeId')
            }, this);

            this.sortable = this.sortable || this.params.sortable;

            this.iconHtml = this.getHelper().getScopeColorIconHtml(this.foreignScope);

            this.addActionHandler('selectLink', function () {
                self.notify('Loading...');
                var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView;

                this.createView('dialog', viewName, {
                    scope: this.foreignScope,
                    createButton: !this.createDisabled && this.mode !== 'search',
                    filters: this.getSelectFilters(),
                    boolFilterList: this.getSelectBoolFilterList(),
                    boolFilterData: this.getBoolFilterData(),
                    primaryFilterName: this.getSelectPrimaryFilterName(),
                    multiple: this.linkMultiple,
                    whereAdditional: this?.options?.whereAdditional || this.model.getFieldParam(this.name, 'where') || undefined,
                    massRelateEnabled: true,
                    createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                    mandatorySelectAttributeList: this.mandatorySelectAttributeList,
                    forceSelectAllAttributes: this.forceSelectAllAttributes,
                    selectAllByDefault: this.getSelectAllByDefault(),
                    sortBy: this.sortBy,
                    sortAsc: this.sortAsc
                }, function (dialog) {
                    dialog.render();
                    self.notify(false);

                    this.listenTo(dialog, 'select', function (models) {
                        if (this.foreignScope !== 'File') {
                            this.clearView('dialog');
                        }
                        if (models.massRelate) {
                            if (models.where.length === 0) {
                                // force subquery if primary filter "all" is used in modal
                                models.where = [{ asc: true }]
                            }
                            this.model.set(this.idsName, null);
                            this.model.set(this.nameHashName, null);
                            this.ids = [];
                            this.nameHash = {};
                            this.addLinkSubQuery(models);
                            this.trigger('change')
                            return;
                        }
                        if (Object.prototype.toString.call(models) !== '[object Array]') {
                            models = [models];
                        }

                        let selected = this.model.get(this.nameHashName) || {};
                        if ('_localeId' in selected) {
                            delete selected._localeId;
                        }
                        models.forEach(function (model) {
                            if (typeof model.get !== "undefined") {
                                let foreignName = self.getForeignName();
                                selected[model.id] = self.getLocalizedFieldValue(model, foreignName);
                            } else if (model.name) {
                                selected[model.id] = model.name;
                            } else {
                                selected[model.id] = model.id;
                            }
                        });

                        this.model.set(this.idsName, Object.keys(selected));
                        this.model.set(this.nameHashName, selected);

                        this.ids = Object.keys(selected);
                        this.nameHash = selected;
                        this.nameHash._localeId = this.getUser().get('localeId');

                        this.trigger('change');
                        this.deleteLinkSubQuery();
                        this.reRender();
                    });

                    this.listenTo(dialog, 'unselect', id => {
                        self.deleteLink(id);
                    });

                }, this);
            });

            this.addActionHandler('uploadLink', function () {
                this.uploadLink();
            });

            this.events['click a[data-action="clearLink"]'] = function (e) {
                var id = $(e.currentTarget).attr('data-id');
                this.deleteLink(id);
            };
            this.events['click a[data-action="clearLinkSubQuery"]'] = function (e) {
                this.deleteLinkSubQuery();
            };

            this.addActionHandler('createLink', function () {
                this.notify('Loading...');
                this.createView('quickCreate', 'views/modals/edit', {
                    scope: this.foreignScope,
                    fullFormDisabled: true,
                    attributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                }, view => {
                    view.once('after:render', () => {
                        this.notify(false);
                    });
                    view.render();

                    this.listenToOnce(view, 'after:save', function (model) {
                        this.clearView('quickCreate');
                        this.addLink(model.id, this.getModelTitle(model));
                    }.bind(this));
                });
            });
        },

        uploadLink: function () {
            let attributes = this.getCreateAttributes() || {};

            if (this.defs.entityModel) {
                if (this.getMetadata().get(['scopes', 'File', 'hasOwner'])) {
                    attributes.ownerUserId = this.getUser().id;
                    attributes.ownerUserName = this.getUser().get('name');
                }
                if (this.getMetadata().get(['scopes', 'File', 'hasAssignedUser'])) {
                    attributes.assignedUserId = this.getUser().id;
                    attributes.assignedUserName = this.getUser().get('name');
                }
                if (this.getMetadata().get(['scopes', 'File', 'hasTeam'])) {
                    attributes.teamsIds = this.defs.entityModel.get('teamsIds') || null;
                    attributes.teamsNames = this.defs.entityModel.get('teamsNames') || null;
                }
            }

            this.notify('Loading...');
            this.createView('upload', 'views/file/modals/upload', {
                scope: 'File',
                fullFormDisabled: true,
                layoutName: 'upload',
                multiUpload: true,
                attributes: attributes,
            }, view => {
                view.render();
                this.notify(false);
                this.listenTo(view.model, 'after:file-upload', entity => {
                    this.addLink(entity.id, entity.name);
                });
                this.listenTo(view.model, 'after:delete-action', id => this.deleteLink(id));

                this.listenToOnce(view, 'close', () => {
                    this.clearView('upload');
                });
            });
        },

        handleSearchType: function (type) {
            if (~['anyOf', 'noneOf'].indexOf(type)) {
                this.$el.find('div.link-group-container').removeClass('hidden');
            } else {
                this.$el.find('div.link-group-container').addClass('hidden');
            }
        },

        setupSearch: function () {
            this.searchData.subQuery = this.searchParams.subQuery || [];
            this.events = _.extend({
                'change select.search-type': function (e) {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        getAutocompleteUrl: function (q) {
            const [name] = this.getLocalizedFieldData(this.foreignScope, this.getNameField(this.foreignScope))
            var url = this.foreignScope + '?collectionOnly=true&asc=true&sortBy=' + name + '&maxSize=' + this.AUTOCOMPLETE_RESULT_MAX_COUNT,
                boolList = this.getSelectBoolFilterList();
            where = [];


            if (boolList && Array.isArray(boolList) && boolList.length > 0) {
                url += '&' + $.param({ 'boolFilterList': boolList });
            }
            var primary = this.getSelectPrimaryFilterName();
            if (primary) {
                url += '&' + $.param({ 'primaryFilter': primary });
            }

            where.push({ 'type': 'textFilter', value: 'AUTOCOMPLETE:' + q });

            let additionalWhere = this.getAutocompleteAdditionalWhereConditions() || [];
            if (Array.isArray(additionalWhere) && additionalWhere.length) {
                additionalWhere.forEach(whereClause => {
                    where.push(whereClause);
                })
            }

            if (where.length) {
                url += '&' + $.param({ 'where': where });
            }

            return url;
        },

        prepareAutocompleteQueryText(q) {
            if (q.includes('*')) {
                q = q.replace(/\*/g, '%');
            }

            if (!q.includes('%')) {
                q += '%';
            }

            return q;
        },

        getAutocompleteAdditionalWhereConditions: function () {
            return [];
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit' || this.mode === 'search') {
                this.$element = this.$el.find('input.main-element');

                const data = [];
                this.ids.forEach(function (id) {
                    data.push({
                        id: id,
                        name: this.nameHash[id] ?? id
                    });
                }, this);

                this.$element.val(this.ids.join(':,:'));

                const selectizeOptions = {
                    delimiter: ':,:',
                    options: data,
                    valueField: 'id',
                    labelField: 'name',
                    searchField: 'name',
                    create: false,
                    maxItems: null,
                    preload: false,
                    persist: false,
                    loadThrottle: 300,
                    plugins: {
                        remove_button: {
                            label: ''
                        }
                    },
                    onItemAdd: (value, item) => {
                        if (item && item.size() > 0) {
                            this.addLink(value, item.find('span').text());
                        }
                    },
                    onItemRemove: (value) => {
                        if (value === 'subquery') {
                            this.deleteLinkSubQuery();
                        } else {
                            this.deleteLink(value);
                        }
                    },
                    onChange: (value) => {
                        const items = value.split(':,:').filter(item => !!item?.trim());
                        this.ids = Espo.Utils.clone(items);
                        this.trigger('change');
                    },
                    render: {
                        item: (data, escape) => {
                            return `<div class="item" title="${escape(data.name)}"><span>${escape(data.name)}</span></div>`;
                        },
                    }
                };

                if (!this.autocompleteDisabled) {
                    selectizeOptions.load = (query, callback) => {
                        if (!query.length) return callback();

                        this.ajaxGetRequest(this.getAutocompleteUrl(query))
                            .success(function (res) {
                                if (!res.list || res.list.length === 0) {
                                    callback();
                                }

                                const list = [];
                                const name = this.getNameField(this.foreignScope)
                                const [localizedName] = this.getLocalizedFieldData(this.foreignScope, name)
                                res.list.forEach(function (item) {
                                    const value = item[localizedName] || item[name];
                                    list.push({
                                        id: item.id,
                                        name: value ?? '',
                                        data: item.id,
                                        value: value
                                    });
                                }, this);

                                callback(list);
                            })
                            .error(() => callback());
                    };
                }

                if (this.mode === 'edit') {
                    if (this.sortable) {
                        selectizeOptions.plugins.drag_drop = {};
                    }
                }

                this.$element.selectize(selectizeOptions);

                if (this.mode === 'search') {
                    this.addLinkSubQueryHtml(this.searchData.subQuery);
                    const type = this.$el.find('select.search-type').val();
                    this.handleSearchType(type);
                }
            }

            if (!this.model.has(this.idsName)) {
                this.getCellElement().attr('data-no-load', true);
            } else {
                this.getCellElement().removeAttr('data-no-load')
            }
        },

        toggleVisibility(name) {
            Dep.prototype.toggleVisibility.call(this, name);
            Dep.prototype.toggleVisibility.call(this, name + 'Ids');
        },

        renderLinks: function () {
            this.ids.forEach(function (id) {
                this.addLinkHtml(id, this.nameHash[id]);
            }, this);
        },

        deleteLinkSubQuery: function () {
            this.deleteLinkSubQueryHtml();
            if (this.searchData) {
                this.searchData.subQuery = [];
            }
            this.trigger('clear-subquery');
        },

        deleteLink: function (id) {
            this.deleteLinkHtml(id);

            var index = this.ids.indexOf(id);

            if (index > -1) {
                this.ids.splice(index, 1);
            }
            delete this.nameHash[id];
            this.afterDeleteLink(id);
            this.trigger('change');
        },

        addLinkSubQuery: function (data, silent = false) {
            if (!this.searchData) {
                return;
            }
            let subQuery = Espo.Utils.clone(data.where ?? []);
            this.searchData.subQuery = subQuery;
            this.addLinkSubQueryHtml(subQuery);
            if (!silent) {
                this.trigger('add-subquery', subQuery);
            }
        },

        addLink: function (id, name) {
            if (!~this.ids.indexOf(id)) {
                this.ids.push(id);
                this.nameHash[id] = name;
                this.addLinkHtml(id, name);
                this.afterAddLink(id);
            }
            this.trigger('change');
        },

        afterDeleteLink: function (id) {
        },

        afterAddLink: function (id) {
        },

        deleteLinkHtml: function (id) {
            if (this.$element && this.$element.size() > 0) {
                const selectize = this.$element[0].selectize;
                selectize.removeOption(id);
            }
        },

        deleteLinkSubQueryHtml: function () {
            this.deleteLink('subquery');
        },

        addLinkSubQueryHtml: function (subQuery) {
            if (!subQuery || subQuery.length === 0) {
                return;
            }
            this.deleteLinkSubQueryHtml();
            this.addLinkHtml('subquery', '(Subquery)');
        },

        addLinkHtml: function (id, name) {
            if (this.$element && this.$element.size() > 0) {
                const selectize = this.$element[0].selectize;
                selectize.registerOption({
                    id, name
                });

                let value = selectize.getValue();
                if (Array.isArray(value)) {
                    value.push(id);
                } else {
                    value = value?.split(':,:') ?? [];
                    value.push(id);
                }

                selectize.setValue(value);
            }
        },

        getIconHtml: function (id) {
            return this.iconHtml;
        },

        empty() {
            this.model.set(this.idsName, []);
            this.model.set(this.nameHashName, {});
            this.model.set(this.typeHashName, {});
        },

        getDetailLinkHtml: function (id) {
            var name = this.nameHash[id] || id;
            if (!name && id) {
                name = this.translate(this.foreignScope, 'scopeNames');
            }
            var iconHtml = '';
            if (this.mode == 'detail') {
                iconHtml = this.getIconHtml(id);
            }
            return '<a href="#' + this.foreignScope + '/view/' + id + '">' + iconHtml + Handlebars.Utils.escapeExpression(name) + '</a>';
        },

        getValueForDisplay: function () {
            if (this.mode == 'detail' || this.mode == 'list') {
                var names = [];
                this.ids.forEach(function (id) {
                    names.push(this.getDetailLinkHtml(id));
                }, this);
                if (names.length) {
                    return '<div class="value-container"><span>' + names.join('</span>, <span>') + '</span></div>';
                }
            }
        },

        validateRequired: function () {
            if (this.isRequired()) {
                var idList = this.model.get(this.idsName) || [];
                if (idList.length == 0) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        fetch: function () {
            let data = {};
            data[this.idsName] = (this.ids || []).length ? this.ids : null;
            data[this.nameHashName] = Object.keys(this.nameHash || {}).length ? this.nameHash : null;

            return data;
        },

        fetchFromDom: function () {
            this.ids = [];
            this.$el.find('.link-container').children().each(function (i, li) {
                var id = $(li).attr('data-id');
                if (!id) return;
                this.ids.push(id);
            }.bind(this));
        },

        clearSearch: function () {
            this.ids = [];

            this.reRender();
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();

            if (type === 'anyOf') {
                var idList = this.ids || [];

                var data = {
                    type: 'linkedWith',
                    value: idList,
                    nameHash: this.nameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
                if (!idList.length) {
                    data.value = null;
                }
                return data;
            } else if (type === 'noneOf') {
                var values = this.ids || [];

                var data = {
                    type: 'notLinkedWith',
                    value: this.ids || [],
                    nameHash: this.nameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
                return data;
            } else if (type === 'isEmpty') {
                var data = {
                    type: 'isNotLinked',
                    data: {
                        type: type
                    }
                };
                return data;
            } else if (type === 'isNotEmpty') {
                var data = {
                    type: 'isLinked',
                    data: {
                        type: type
                    }
                };
                return data;
            }
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type || 'anyOf';
        },

        getSelectAllByDefault: function () {
            return false;
        },

        createFilterView(rule, inputName, type, delay = true) {
            const scope = this.model.urlRoot;
            this.filterValue = null;
            if (!this.setTimeoutFunction) {
                this.setTimeoutFunction = {}
            }
            let createViewField = (model) => {
                this.clearView(inputName);
                let operator = rule.operator.type;
                if (['linked_with', 'not_linked_with', 'array_any_of', 'array_none_of',].includes(operator)) {
                    const attribute = this.defs.params.attribute ?? null;
                    let foreignScope = this.defs.params.foreignScope
                        ?? this.foreignScope
                        ?? this.getMetadata().get(['entityDefs', scope, 'fields', this.name, 'entity'])
                        ?? this.getMetadata().get(['entityDefs', scope, 'links', this.name, 'entity']);
                    if (attribute && attribute.entityType && attribute.entityType.length) {
                        foreignScope = attribute.entityType;
                    }

                    let view = 'views/fields/link-multiple';
                    if (type === 'extensibleMultiEnum') {
                        view = 'views/fields/extensible-multi-enum'
                    }

                    this.createView(inputName, view, {
                        name: 'value',
                        el: `#${rule.id} .field-container`,
                        model: model,
                        mode: 'search',
                        foreignScope: foreignScope,
                        hideSearchType: true,
                        whereAdditional: this.model.getFieldParam(this.getAttributeFieldName(), 'where') || undefined,
                    }, view => {
                        view.selectBoolFilterList = this.selectBoolFilterList;
                        view.boolFilterData = {};
                        view.getSelectFilters = () => {
                            let bool = {};
                            let queryBuilder = {
                                condition: "AND",
                                rules: [],
                                valid: true
                            }
                            let subQuery = rule.data?.subQuery || [];
                            subQuery.forEach(item => {
                                if (item.type === 'bool') {
                                    item.value.forEach(v => bool[v] = true);
                                }

                                if (item.condition) {
                                    queryBuilder.rules.push(item);
                                }
                            });

                            if (queryBuilder.rules.length === 1) {
                                queryBuilder = queryBuilder.rules[0];
                            }

                            return { bool, queryBuilder, queryBuilderApplied: true }
                        }

                        view.getAutocompleteAdditionalWhereConditions = () => {
                            let boolData = this.getBoolFilterData();
                            // add boolFilter data
                            if (boolData) {
                                return [
                                    {
                                        'type': 'bool',
                                        'data': boolData
                                    }
                                ];
                            }

                            return [];
                        }

                        view.getSelectAllByDefault = () => {
                            let subQuery = rule.data?.subQuery || [];
                            return subQuery.length > 0;
                        }

                        for (const key in this.boolFilterData) {
                            if (typeof this.boolFilterData[key] === 'function') {
                                view.boolFilterData[key] = this.boolFilterData[key].bind(this);
                            }
                        }

                        if (rule.data && rule.data['subQuery']) {
                            let data = { where: rule.data['subQuery'] };
                            this.listenTo(view, 'after:render', () => {
                                view.addLinkSubQuery(data);
                            })
                        }

                        this.listenTo(view, 'add-subquery', subQuery => {
                            this.filterValue = rule.value ?? [];
                            if (!rule.data) {
                                rule.data = {}
                            }
                            rule.data['subQuery'] = subQuery;
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });

                        this.listenTo(view, 'clear-subquery', () => {
                            this.filterValue = rule.value;
                            if (rule.value !== null && rule.length === 0) {
                                this.filterValue = null;
                            }
                            delete rule.data['subQuery'];
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });

                        this.listenTo(view, 'change', () => {
                            this.filterValue = view.ids ?? model.get('valueIds');
                            if (!rule.data) {
                                rule.data = {};
                            }
                            rule.data['nameHash'] = view.nameHash ?? model.get('valueNameHash');
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });
                        view.render();
                        this.renderAfterEl(view, `#${rule.id} .field-container`);
                    });

                }
            };

            this.getModelFactory().create(null, model => {
                this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                    setTimeout(() => {
                        let nameHash = rule.data?.nameHash
                        if (nameHash?._localeId &&
                            nameHash?._localeId !== this.getUser().get('localeId') &&
                            (rule.value || []).length > 0) {
                            const resp = this.ajaxGetRequest(this.foreignScope, {
                                where: [
                                    {
                                        type: 'in',
                                        attribute: 'id',
                                        value: rule.value
                                    }
                                ]
                            }, { async: false })

                            nameHash = { '_localeId': this.getUser().get('localeId') }
                            const foreignName = this.getForeignName();
                            const localizedForeignName = this.getLocalizedFieldData(this.foreignScope, foreignName)[0]
                            resp.responseJSON.list.forEach(record => {
                                nameHash[record.id] = record[localizedForeignName] || record[foreignName]
                            })
                        }
                        model.set('valueNames', nameHash);
                        model.set('valueIds', rule.value);
                        if (type === 'extensibleMultiEnum') {
                            model.set('value', rule.value);
                        }
                        let view = this.getView(inputName);
                        if (rule.data && rule.data['subQuery'] && view) {
                            let data = { where: rule.data['subQuery'] };
                            view.addLinkSubQuery(data, true);
                        }
                        if (view) {
                            view.reRender();
                        }
                    }, 200);
                });
                if (delay) {
                    this.setTimeoutFunction[inputName] = setTimeout(() => {
                        createViewField(model);
                        clearTimeout(this.setTimeoutFunction);
                        this.setTimeoutFunction[inputName] = null;
                    }, 50)
                } else {
                    if (this.setTimeoutFunction[inputName]) {
                        clearTimeout(this.setTimeoutFunction[inputName]);
                        this.setTimeoutFunction[inputName] = null;
                    }
                    createViewField(model);
                }
            });
        },

        createQueryBuilderFilter(type = null) {
            let operators = [
                'linked_with',
                'not_linked_with',
            ];

            if (type === 'extensibleMultiEnum') {
                operators = [
                    'array_any_of',
                    'array_none_of',
                    'is_null',
                    'is_not_null'
                ];
            } else {
                if (this.getForeignScope() === 'User') {
                    operators = operators.concat(['is_team_member', 'include_me', 'exclude_me'])
                }
                operators = operators.concat(['is_not_linked', 'is_linked']);
            }

            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                realType: type,
                optgroup: this.getLanguage().translate('Fields'),
                operators: operators,
                input: (rule, inputName) => {
                    if (!rule || !inputName) {
                        return '';
                    }

                    if (!this.isNotListeningToOperatorChange) {
                        this.isNotListeningToOperatorChange = {}
                    }

                    if (!this.initialOperatorType) {
                        this.initialOperatorType = {}
                    }
                    this.initialOperatorType[inputName] = rule.operator.type;

                    this.createFilterView(rule, inputName, type, true);

                    if (!this.isNotListeningToOperatorChange[inputName]) {
                        this.listenTo(this.model, 'afterUpdateRuleOperator', rule => {
                            if (rule.$el.find('.rule-value-container > input').attr('name') !== inputName) {
                                return;
                            }

                            if (this.name !== rule.filter.id) {
                                return;
                            }


                            if (rule.operator.type === this.initialOperatorType[inputName]) {
                                this.initialOperatorType[inputName] = null;
                                return;
                            }

                            this.clearView(inputName);
                            this.createFilterView(rule, inputName, type);
                        });
                        this.listenTo(this.model, 'beforeUpdateRuleFilter', rule => {
                            if (rule.data && rule.data['subQuery']) {
                                delete rule.data['subQuery'];
                            }
                        });

                        this.listenTo(this.model, 'afterUpdateRuleFilter', rule => {
                            const view = this.getView(inputName);
                            if (view && rule.data && rule.data.nameHash) {
                                view.model.set('valueNames', rule.data.nameHash);
                            }
                        });

                        this.isNotListeningToOperatorChange[inputName] = true;
                    }

                    return `<div class="field-container"></div><input type="hidden" name="${inputName}" />`;
                },
                valueGetter: this.filterValueGetter.bind(this),
                validation: {
                    callback: function (value, rule) {
                        if (!Array.isArray(value) || (value.length === 0 && !rule.data?.subQuery)) {
                            return 'bad list';
                        }

                        return true;
                    }.bind(this),
                }
            };
        },

        getForeignScope: function () {
            const scope = this.model.urlRoot;
            return this.defs.params.foreignScope
                ?? this.foreignScope
                ?? this.getMetadata().get(['entityDefs', scope, 'links', this.name, 'entity'])
                ?? this.getMetadata().get(['entityDefs', scope, 'fields', this.name, 'entity']);
        },

        listInlineEditModeEnabled() {
            let res = Dep.prototype.listInlineEditModeEnabled.call(this);

            return res && this.model.get(this.idsName) !== null && this.model.get(this.idsName) !== undefined;
        },
    });
});


