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

Espo.define('views/fields/link', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'link',

        listTemplate: 'fields/link/list',

        detailTemplate: 'fields/link/detail',

        editTemplate: 'fields/link/edit',

        searchTemplate: 'fields/link/search',

        nameName: null,

        idName: null,

        foreignScope: null,

        AUTOCOMPLETE_RESULT_MAX_COUNT: 7,

        selectRecordsView: 'views/modals/select-records',

        autocompleteDisabled: false,

        createDisabled: false,

        noCreateScopeList: ['User', 'Team', 'Role'],

        searchTypeList: ['is', 'isEmpty', 'isNotEmpty', 'isNot', 'isOneOf', 'isNotOneOf'],

        selectBoolFilterList: [],

        boolFilterData: {},

        sortBy: null,

        sortAsc: null,

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
            let idValue = this.model.get(this.idName),
                nameValue = this.model.has(this.nameName) ? this.model.get(this.nameName) : idValue;

            if (idValue === this.getConfig().get('systemUserId')) {
                nameValue = this.getLanguage().translate('System')
            }

            if (this.mode === 'detail' && !nameValue && idValue) {
                nameValue = this.translate('None');
            } else if (nameValue === null) {
                nameValue = idValue;
            }

            let iconHtml = null;
            if (this.mode === 'detail') {
                iconHtml = this.getHelper().getScopeColorIconHtml(this.foreignScope);
            }

            if (this.options.isKanban) {
                idValue = null;
            }

            if (this.mode === 'edit') {
                if (!nameValue && idValue) {
                    nameValue = idValue;
                }
            }

            return _.extend({
                idName: this.idName,
                nameName: this.nameName,
                idValue: idValue,
                nameValue: nameValue,
                foreignScope: this.foreignScope,
                valueIsSet: this.model.has(this.idName),
                iconHtml: iconHtml,
                createDisabled: this.createDisabled,
            }, Dep.prototype.data.call(this));
        },

        getSelectFilters: function () {
            // Example:
            // if (this.model.get('projectId')) {
            //     return {
            //         queryBuilder: {
            //             condition: 'AND',
            //             rules: [
            //                 {
            //                     id: 'projectId',
            //                     field: 'projectId',
            //                     type: 'string',
            //                     operator: 'in',
            //                     value: [this.model.get('projectId')],
            //                     data: {
            //                         nameHash: {
            //                             [this.model.get('projectId')]: this.model.get('projectName')
            //                         }
            //                     }
            //                 }
            //             ],
            //             valid: true
            //         },
            //         queryBuilderApplied: true
            //     }
            // }
        },

        getSelectBoolFilterList: function () {
            return Espo.Utils.cloneDeep(this.selectBoolFilterList);
        },

        getSelectPrimaryFilterName: function () {
            return this.selectPrimaryFilterName;
        },

        getCreateAttributes: function () {
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.selectBoolFilterList = Espo.Utils.cloneDeep(this.selectBoolFilterList)

            if (this.nameName === null) {
                this.nameName = this.name + 'Name';
            }

            if (this.idName === null) {
                this.idName = this.name + 'Id';
            }

            this.foreignScope = this.options.foreignScope || this.foreignScope;
            this.foreignScope = this.foreignScope || this.model.getFieldParam(this.name, 'entity') || this.model.getLinkParam(this.name, 'entity');
            if (this.model.getFieldParam(this.name, 'entityNameField')) {
                this.foreignScope = this.model.get(this.model.getFieldParam(this.name, 'entityNameField'));
            }

            // prepare default value
            const fieldDefs = this.model.defs.fields[this.name] || null;
            let foreignId = this.model.get(this.idName) || (fieldDefs?.defaultId);
            if ((this.model.get(this.name + 'HasDefaultAttributes') || (fieldDefs && (fieldDefs.defaultAttributes || fieldDefs.defaultId))) && this.mode === 'edit' && !this.model.get('id') && foreignId && this.foreignScope) {
                this.model.set(this.idName, null);
                this.model.set(this.nameName, null);
                this.ajaxGetRequest(this.foreignScope + '/' + foreignId, { silent: true })
                    .done(function (response) {
                        this.model.set(this.idName, response.id);
                        this.model.set(this.nameName, response.name);
                        this.reRender();
                    }.bind(this))
                    .always(function (error) {
                        this.trigger('linkLoaded');
                    }.bind(this));
            }

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

            this.addActionHandler('selectLink', function () {
                this.selectLink();
            });
            this.addActionHandler('clearLink', function () {
                this.clearLink();
            });
            this.addActionHandler('createLink', function () {
                const attributes = _.extend((this.getCreateAttributes() || {}), {
                    _entityFrom: _.extend(this.model.attributes, {
                        _entityName: this.model.name,
                        _createLinkName: this.name
                    })
                });
                if (this.getMetadata().get(['scopes', this.foreignScope, 'hasOwner'])) {
                    attributes.ownerUserId = this.getUser().id;
                    attributes.ownerUserName = this.getUser().get('name');
                }
                if (this.getMetadata().get(['scopes', this.foreignScope, 'hasAssignedUser'])) {
                    attributes.assignedUserId = this.getUser().id;
                    attributes.assignedUserName = this.getUser().get('name');
                }
                if (this.getMetadata().get(['scopes', this.foreignScope, 'hasTeam'])) {
                    attributes.teamsIds = this.model.get('teamsIds') || null;
                    attributes.teamsNames = this.model.get('teamsNames') || null;
                }

                this.notify('Loading...');
                this.createView('quickCreate', 'views/modals/edit', {
                    scope: this.foreignScope,
                    fullFormDisabled: true,
                    attributes: (this.mode === 'edit') ? attributes : null,
                }, view => {
                    view.once('after:render', () => {
                        this.notify(false);
                    });
                    view.render();

                    this.listenToOnce(view, 'after:save', function (model) {
                        this.clearView('quickCreate');
                        this.select(model);
                    }.bind(this));
                });
            });
        },

        select: function (model) {
            let foreignName = this.model.getLinkParam(this.name, 'foreignName') || this.model.getFieldParam(this.name, 'foreignName') || 'name';
            const nameValue = this.getLocalizedFieldValue(model, foreignName);

            this.$elementName.attr('value', nameValue);
            this.$elementName.val(nameValue || this.translate('None'));
            this.$elementId.val(model.get('id'));
            if (this.mode === 'search') {
                this.searchData.idValue = model.get('id');
                this.searchData.nameValue = this.getModelTitle(model);
            }
            this.trigger('change');
        },

        selectLink: function () {
            this.notify('Loading...');

            var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView;
            this.createView('dialog', viewName, {
                scope: this.foreignScope,
                createButton: !this.createDisabled && this.mode != 'search',
                filters: this.getSelectFilters(),
                boolFilterList: this.getSelectBoolFilterList(),
                boolFilterData: this.getBoolFilterData(),
                primaryFilterName: this.getSelectPrimaryFilterName(),
                whereAdditional: this.model.getFieldParam(this.name, 'where') || undefined,
                createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                mandatorySelectAttributeList: this.mandatorySelectAttributeList,
                forceSelectAllAttributes: this.forceSelectAllAttributes,
                sortBy: this.sortBy,
                sortAsc: this.sortAsc
            }, function (view) {
                view.render();
                this.notify(false);
                this.listenToOnce(view, 'select', function (model) {
                    this.clearView('dialog');
                    this.select(model);
                }, this);
            }, this);
        },

        clearLink: function () {
            if (this.$elementName) {
                this.$elementName.val('');
                this.$elementId.val('');
            }
            this.trigger('change');
        },

        setupSearch: function () {
            this.searchData.oneOfIdList = this.searchParams.oneOfIdList || [];
            this.searchData.oneOfNameHash = this.searchParams.oneOfNameHash || {};
            this.searchData.subQuery = this.searchParams.subQuery || [];
            this.searchData.idValue = this.getSearchParamsData().idValue || this.searchParams.idValue || this.searchParams.value;
            this.searchData.nameValue = this.getSearchParamsData().nameValue || this.searchParams.valueName;

            this.events = _.extend({
                'change select.search-type': function (e) {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        handleSearchType: function (type) {
            if (~['is', 'isNot', 'isNotAndIsNotEmpty'].indexOf(type)) {
                this.$el.find('div.primary').removeClass('hidden');
            } else {
                this.$el.find('div.primary').addClass('hidden');
            }

            if (~['isOneOf', 'isNotOneOf', 'isNotOneOfAndIsNotEmpty'].indexOf(type)) {
                this.$el.find('div.one-of-container').removeClass('hidden');
            } else {
                this.$el.find('div.one-of-container').addClass('hidden');
            }
        },

        getAutocompleteUrl: function (q) {
            const [name] = this.getLocalizedFieldData(this.foreignScope, this.getNameField(this.foreignScope))
            let url = this.foreignScope + '?collectionOnly=true&sortBy=' + name + '&maxSize=' + this.AUTOCOMPLETE_RESULT_MAX_COUNT,
                where = [];

            let boolList = this.getSelectBoolFilterList();
            if (boolList && Array.isArray(boolList) && boolList.length > 0) {
                url += '&' + $.param({ 'boolFilterList': boolList });
            }

            let primary = this.getSelectPrimaryFilterName();
            if (primary) {
                url += '&' + $.param({ 'primaryFilter': primary });
            }

            let boolData = this.getBoolFilterData();
            if (boolData && Object.keys(boolData).length > 0) {
                where.push({ 'type': 'bool', 'data': boolData });
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

            if (this.foreignScope === 'User' && this.model.getFieldParam(this.name, 'currentUserAsDefault') && (this.mode === 'edit' || this.mode === 'detail') && !this.model.get('id')) {
                this.model.set(this.idName, this.getUser().get('id'));
                this.model.set(this.nameName, this.getUser().get('name'));
            }

            if (this.mode == 'edit' || this.mode == 'search') {
                this.$elementId = this.$el.find('input[name="' + this.idName + '"]');
                this.$elementName = this.$el.find('input[name="' + this.nameName + '"]');

                this.$elementName.on('change', function () {
                    if (this.$elementName.val() == '') {
                        this.$elementName.val('');
                        this.$elementId.val('');
                        this.trigger('change');
                    }
                }.bind(this));

                if (this.mode == 'edit') {
                    this.$elementName.on('blur', function (e) {
                        if (this.model.has(this.nameName)) {
                            e.currentTarget.value = this.model.get(this.nameName);
                        }
                    }.bind(this));
                } else if (this.mode == 'search') {
                    this.$elementName.on('blur', function (e) {
                        e.currentTarget.value = this.$elementName.attr('value');
                    }.bind(this));
                }

                var $elementName = this.$elementName;

                if (!this.autocompleteDisabled) {
                    this.$elementName.autocomplete({
                        serviceUrl: function (q) {
                            return this.getAutocompleteUrl(q);
                        }.bind(this),
                        ignoreParams: true,
                        minChars: 1,
                        autoSelectFirst: true,
                        formatResult: function (suggestion) {
                            return Handlebars.Utils.escapeExpression(suggestion.name);
                        }.bind(this),
                        transformResult: function (response) {
                            var response = JSON.parse(response);
                            var list = [];
                            const name = this.getNameField(this.foreignScope)
                            const [localizedName] = this.getLocalizedFieldData(this.foreignScope, name);

                            response.list.forEach(function (item) {
                                const value = item[localizedName] || item[name];
                                list.push({
                                    id: item.id,
                                    name: value || '',
                                    data: item.id,
                                    value: value,
                                    attributes: item
                                });
                            }, this);
                            return {
                                suggestions: list
                            };
                        }.bind(this),
                        onSelect: function (s) {
                            this.getModelFactory().create(this.foreignScope, function (model) {
                                model.set(s.attributes);
                                this.select(model);
                            }, this);
                        }.bind(this)
                    });

                    this.once('render', function () {
                        $elementName.autocomplete('dispose');
                    }, this);

                    this.once('remove', function () {
                        $elementName.autocomplete('dispose');
                    }, this);


                    if (this.mode == 'search') {
                        var $elementOneOf = this.$el.find('input.element-one-of');
                        $elementOneOf.autocomplete({
                            serviceUrl: function (q) {
                                return this.getAutocompleteUrl(q);
                            }.bind(this),
                            minChars: 1,
                            ignoreParams: true,
                            formatResult: function (suggestion) {
                                return Handlebars.Utils.escapeExpression(suggestion.name);
                            },
                            transformResult: function (response) {
                                var response = JSON.parse(response);
                                var list = [];
                                const name = this.getNameField(this.foreignScope)
                                const [localizedName] = this.getLocalizedFieldData(this.foreignScope, name);

                                response.list.forEach(function (item) {
                                    const value = item[localizedName] || item[name];
                                    list.push({
                                        id: item.id,
                                        name: value ?? '',
                                        data: item.id,
                                        value: value
                                    });
                                }, this);
                                return {
                                    suggestions: list
                                };
                            }.bind(this),
                            onSelect: function (s) {
                                this.addLinkOneOf(s.id, s.name);
                                $elementOneOf.val('');
                            }.bind(this)
                        });


                        this.once('render', function () {
                            $elementOneOf.autocomplete('dispose');
                        }, this);

                        this.once('remove', function () {
                            $elementOneOf.autocomplete('dispose');
                        }, this);
                    }
                }

                $elementName.on('change', function () {
                    if (!this.model.get(this.idName)) {
                        $elementName.val(this.model.get(this.nameName));
                    }
                }.bind(this));
            }

            if (this.mode == 'search') {
                var type = this.$el.find('select.search-type').val();
                this.handleSearchType(type);

                if (~['isOneOf', 'isNotOneOf', 'isNotOneOfAndIsNotEmpty'].indexOf(type)) {
                    this.addLinkSubQueryHtml(this.searchData.subQuery);
                    this.searchData.oneOfIdList.forEach(function (id) {
                        this.addLinkOneOfHtml(id, this.searchData.oneOfNameHash[id]);
                    }, this);

                    if (Array.isArray(this.searchData.oneOfIdList) && this.searchData.oneOfIdList.length > 0) {
                        this.getCollectionFactory().create(this.foreignScope, function (collection) {
                            const whereCondition = [
                                {
                                    'type': 'in',
                                    'attribute': 'id',
                                    'value': this.searchData.oneOfIdList
                                }
                            ];
                            collection.fetch({ data: $.param({ silent: true, where: whereCondition }) }).then(res => {
                                for (const idItem of this.searchData.oneOfIdList) {
                                    const model = collection.get(idItem);
                                    if (model && this.getModelTitle(model)) {
                                        this.replaceNameOneOf(idItem, this.getModelTitle(model))
                                    }
                                }
                            });
                        }, this);
                    }
                }

                if (~['is', 'isNot'].indexOf(type)) {
                    if (this.searchData.idValue) {
                        this.getModelFactory().create(this.foreignScope, function (model) {
                            model.set('id', this.searchData.idValue);
                            model.fetch({ data: $.param({ silent: true }) }).then(() => {
                                this.select(model);
                            });
                        }, this);
                    }
                }
            }
        },

        toggleVisibility(name) {
            Dep.prototype.toggleVisibility.call(this, name);
            Dep.prototype.toggleVisibility.call(this, name + 'Id');
        },

        getValueForDisplay: function () {
            return this.model.get(this.nameName);
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.idName) == null) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        deleteLinkOneOf: function (id) {
            this.deleteLinkOneOfHtml(id);

            var index = this.searchData.oneOfIdList.indexOf(id);
            if (index > -1 && this.searchParams.oneOfIdList) {
                this.searchParams.oneOfIdList.splice(index, 1);
            }

            if (this.searchParams.oneOfNameHash && this.searchParams.oneOfNameHash[id]) {
                delete this.searchParams.oneOfNameHash[id];
            }
        },

        deleteLinkSubQuery: function () {
            this.deleteLinkSubQueryHtml();
            if (this.searchData) {
                this.searchData.subQuery = [];
            }
            this.trigger('clear-subquery');
        },

        deleteLinkSubQueryHtml: function () {
            this.$el.find('.link-one-of-container .link-subquery').remove();
        },

        addLinkSubQuery: function (data, silent = false) {
            let subQuery = data.where ?? [];
            this.searchData.subQuery = subQuery;
            this.addLinkSubQueryHtml(subQuery);
            if (!silent) {
                this.trigger('add-subquery', subQuery);
            }
        },

        addLinkOneOf: function (id, name) {
            if (!~this.searchData.oneOfIdList.indexOf(id)) {
                this.searchData.oneOfIdList.push(id);
                this.searchData.oneOfNameHash[id] = name;
                this.addLinkOneOfHtml(id, name);
            }
        },

        replaceNameOneOf: function (id, name) {
            const $el = this.$el.find('.link-one-of-container .link-' + id);
            if ($el) {
                $el.html(name + '&nbsp');
                $el.prepend('<a href="javascript:" class="pull-right" data-id="' + id + '" data-action="clearLinkOneOf"><i class="ph ph-x"></i></a>');
            }
            this.searchData.oneOfNameHash[id] = name;
        },

        deleteLinkOneOfHtml: function (id) {
            this.$el.find('.link-one-of-container .link-' + id).remove();
        },

        addLinkOneOfHtml: function (id, name) {
            var $container = this.$el.find('.link-one-of-container');
            var $el = $('<div />').addClass('link-' + id).addClass('list-group-item');
            $el.html(name + '&nbsp');
            $el.prepend('<a href="javascript:" class="pull-right" data-id="' + id + '" data-action="clearLinkOneOf"><i class="ph ph-x"></i></a>');
            $container.append($el);

            return $el;
        },

        addLinkSubQueryHtml: function (subQuery) {
            if (!subQuery || subQuery.length === 0) {
                return;
            }

            this.deleteLinkSubQueryHtml();

            var $container = this.$el.find('.link-one-of-container');
            var $el = $('<div />').addClass('link-subquery').addClass('list-group-item');
            $el.html('(Subquery) &nbsp');
            $el.prepend('<a href="javascript:" class="pull-right" data-action="clearLinkSubQuery"><i class="ph ph-x"></i></a>');
            $container.append($el);

            return $el;
        },

        fetch: function () {
            var data = {};
            data[this.nameName] = this.$el.find('[name="' + this.nameName + '"]').val() || null;
            data[this.idName] = this.$el.find('[name="' + this.idName + '"]').val() || null;

            return data;
        },

        clearSearch: function () {
            this.searchData.idValue = null;
            this.searchData.nameValue = null;
            this.searchData.oneOfIdList = [];
            this.searchData.oneOfNameHash = {};
            this.searchData.subQuery = [];

            this.reRender();
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();
            var value = this.$el.find('[name="' + this.idName + '"]').val();

            if (type == 'isEmpty') {
                var data = {
                    type: 'isNull',
                    field: this.idName,
                    data: {
                        type: type
                    }
                };
                return data;
            } else if (type == 'isNotEmpty') {
                var data = {
                    type: 'isNotNull',
                    field: this.idName,
                    data: {
                        type: type
                    }
                };
                return data;
            } else if (type == 'isOneOf') {
                var data = {
                    type: 'in',
                    field: this.idName,
                    value: this.searchData.oneOfIdList,
                    oneOfIdList: this.searchData.oneOfIdList,
                    oneOfNameHash: this.searchData.oneOfNameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
                return data;
            } else if (type == 'isNotOneOf') {
                var data = {
                    type: 'or',
                    value: [
                        {
                            type: 'notIn',
                            attribute: this.idName,
                            value: this.searchData.oneOfIdList
                        },
                        {
                            type: 'isNull',
                            attribute: this.idName
                        }
                    ],
                    field: this.idName,
                    oneOfIdList: this.searchData.oneOfIdList,
                    oneOfNameHash: this.searchData.oneOfNameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
                return data;
            } else if (type == 'isNotOneOfAndIsNotEmpty') {
                var data = {
                    type: 'notIn',
                    field: this.idName,
                    value: this.searchData.oneOfIdList,
                    oneOfIdList: this.searchData.oneOfIdList,
                    oneOfNameHash: this.searchData.oneOfNameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
                return data;
            } else if (type == 'isNot') {
                var nameValue = this.$el.find('[name="' + this.nameName + '"]').val();
                var data = {
                    type: 'or',
                    value: [
                        {
                            type: 'notEquals',
                            attribute: this.idName,
                            value: value
                        },
                        {
                            type: 'isNull',
                            attribute: this.idName
                        }
                    ],
                    field: this.idName,
                    data: {
                        type: type,
                        idValue: value,
                        nameValue: nameValue
                    }
                };
                return data;
            } else if (type == 'isNotAndIsNotEmpty') {
                var nameValue = this.$el.find('[name="' + this.nameName + '"]').val();
                var data = {
                    type: 'notEquals',
                    field: this.idName,
                    value: value,
                    data: {
                        type: type,
                        idValue: value,
                        nameValue: nameValue
                    }
                };
                return data;
            } else {
                var nameValue = this.$el.find('[name="' + this.nameName + '"]').val();
                var data = {
                    type: 'equals',
                    field: this.idName,
                    value: value,
                    data: {
                        type: type,
                        idValue: value,
                        nameValue: nameValue
                    }
                };
                return data;
            }
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type;
        },

        createFilterView(rule, inputName, type = null, delay = false) {
            const scope = this.model.urlRoot;
            this.filterValue = null;

            let createViewField = (model) => {
                this.clearView(inputName);
                let operator = rule.operator.type;
                if (['in', 'not_in'].includes(operator)) {
                    const attribute = this.defs.params.attribute ?? null;
                    let foreignScope = this.getForeignScope();

                    if (attribute && attribute.entityType && attribute.entityType.length) {
                        foreignScope = attribute.entityType;
                    }

                    this.createView(inputName, 'views/fields/link-multiple', {
                        name: 'value',
                        el: `#${rule.id} .field-container`,
                        model: model,
                        mode: 'search',
                        foreignScope: foreignScope,
                        hideSearchType: true,
                        params: this.defs.params,
                        whereAdditional: this.model.getFieldParam(this.name, 'where') || undefined,
                    }, view => {
                        this.addCustomDataToView(view, rule);

                        this.listenTo(view, 'add-subquery', subQuery => {
                            if(!subQuery || subQuery.length === 0 ) {
                                return;
                            }
                            rule.value = null;
                            this.filterValue = rule.value;

                            (view.ids || []).forEach(id => {
                                if(id === 'subquery') {
                                    return;
                                }

                                view.deleteLink(id);
                            });

                            if (!rule.data || Array.isArray(rule.data)) {
                                rule.data = {}
                            }

                            rule.data['subQuery'] = subQuery;

                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });

                        this.listenTo(view, 'clear-subquery', () => {
                            this.filterValue = rule.value;
                            if (rule.value !== null && rule.value.length === 0) {
                                this.filterValue = null;
                            }
                            if (rule.data) {
                                delete rule.data['subQuery'];
                            }
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });

                        this.listenTo(view, 'change', () => {
                            this.filterValue = (view.ids ?? model.get('valueIds') ?? []).filter(v => v !== 'subquery');
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });

                        this.renderAfterEl(view, `#${rule.id} .field-container`);
                    });
                }
            };

            this.getModelFactory().create(null, model => {
                if (!this.setTimeoutFunction) {
                    this.setTimeoutFunction = {}
                }
                this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                    setTimeout(() => {
                        let nameHash = { '_localeId': this.getUser().get('localeId') }
                        try{
                            const foreignName = this.getMetadata().get(['entityDefs', this.model.urlRoot, 'links', this.name, 'foreignName']) ?? 'name';

                            if ((rule.value || []).length > 0 && foreignName) {
                                const resp = this.ajaxGetRequest(this.foreignScope, {
                                    select: foreignName,
                                    collectionOnly: true,
                                    where: [
                                        {
                                            type: 'in',
                                            attribute: 'id',
                                            value: rule.value
                                        }
                                    ]
                                }, { async: false })

                                const localizedForeignName = this.getLocalizedFieldData(this.foreignScope, foreignName)[0]
                                resp.responseJSON.list.forEach(record => {
                                    nameHash[record.id] = record[localizedForeignName] || record[foreignName]
                                })
                            }
                        }catch (e) {
                            console.error(e)
                        }

                        model.set('valueNames', nameHash);
                        model.set('valueIds', rule.value);

                        if (type === 'extensibleEnum') {
                            model.set('value', rule.value);
                        }

                        view = this.getView(inputName);

                        if (rule.data && rule.data['subQuery'] && view) {
                            let data = { where: Espo.utils.clone(rule.data['subQuery']) };
                            view.searchData.subQuery = Espo.utils.clone(rule.data['subQuery']);
                            view.addLinkSubQuery(data, true);
                        }

                        if(rule.data && rule.data['nameHash']) {
                            delete  rule.data['nameHash'];
                        }

                        if (view) {
                            view.reRender();
                        }
                    }, 200)
                });
                // I am using a delay because after initialisation, the operator is loaded after some delay
                // without the delay you will have the default operator
                if (delay) {
                    this.setTimeoutFunction[inputName] = setTimeout(() => {
                        createViewField(model);
                        clearTimeout(this.setTimeoutFunction);
                        this.setTimeoutFunction[inputName] = null;
                    }, 50)
                } else {
                    if (this.setTimeoutFunction[inputName]) {
                        clearTimeout(this.setTimeoutFunction[inputName]);
                        this.setTimeoutFunction = null;
                    }
                    createViewField(model);
                }

            })
        },

        getQueryBuilderOperators() {
            let operators = ['in', 'not_in'];
            if (this.getForeignScope() === 'User') {
                operators = operators.concat(['is_me', 'is_not_me', 'is_team_member'])
            }

            if (this.getForeignScope() === 'Team') {
                operators = operators.concat(['is_my_team', 'is_not_my_team'])
            }

            operators = operators.concat(['is_null', 'is_not_null']);

            return operators;
        },

        createQueryBuilderFilter(type = null) {
            return {
                id: this.getFilterName(type),
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                optgroup: this.getLanguage().translate('Fields'),
                operators: this.getQueryBuilderOperators(),
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
                    const callback = function (e) {
                        rule.value = null;
                        let view = this.getView(inputName);
                        if (rule.data && rule.data['subQuery']) {
                            delete rule.data['subQuery'];
                            if (view) {
                                view.deleteLinkSubQuery()
                            }
                        }
                        if (view) {
                            view.model.set('valueIds', rule.value);
                            // view.reRender()
                            this.renderAfterEl(view, `#${rule.id} .field-container`);
                        }
                    }.bind(this);

                    if (!this.isNotListeningToOperatorChange[inputName]) {
                        rule.$el.find('.rule-operator-container select').on('change', callback);

                        this.listenTo(this.model, 'afterUpdateRuleOperator', rule => {
                            if (rule.$el.find('.rule-value-container > input').attr('name') !== inputName) {
                                return;
                            }

                            if (this.getFilterName(type) !== rule.filter.id) {
                                return;
                            }

                            if (rule.data) {
                                delete rule.data['subQuery'];
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
                        });

                        this.isNotListeningToOperatorChange[inputName] = true;
                    }

                    return `<div class="field-container"></div><input type="hidden" name="${inputName}" />`;
                },
                valueGetter: this.filterValueGetter.bind(this),
                validation: {
                    callback: function (value, rule) {
                        if ((!Array.isArray(value) || value.length === 0) && !rule.data?.subQuery) {
                            return 'bad list';
                        }

                        return true;
                    }.bind(this),
                }
            };
        },

        chooseMultipleOnSearch: function () {
            return true;
        },

        getFilterName(type = null) {
            let name = this.name;
            if (!name.includes('attr_') && type !== 'extensibleEnum') {
                name = this.name + 'Id'
            }
            return name;
        },

        addCustomDataToView: function (view, rule) {
            view.getSelectFilters = this.getSelectFilters.bind(this);
            view.selectBoolFilterList = this.selectBoolFilterList;
            view.boolFilterData = {};

            for (const key in this.boolFilterData) {
                if (typeof this.boolFilterData[key] === 'function') {
                    view.boolFilterData[key] = this.boolFilterData[key].bind(this);
                }
            }

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

                return { bool, queryBuilder }
            }


            view.getAutocompleteAdditionalWhereConditions = () => {
                let boolData = this.getBoolFilterData();
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

            view.linkMultiple = this.chooseMultipleOnSearch();

            view.getSelectAllByDefault = () => {
                let subQuery = rule.data?.subQuery || [];
                return subQuery.length > 0;
            }
        },

        getForeignScope: function () {
            const scope = this.model.urlRoot;
            return this.defs.params.foreignScope
                ?? this.foreignScope
                ?? this.getMetadata().get(['entityDefs', scope, 'links', this.name, 'entity'])
                ?? this.getMetadata().get(['entityDefs', scope, 'fields', this.name, 'entity']);
        }
    });
});

