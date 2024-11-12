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

Espo.define('views/fields/link-multiple', 'views/fields/base', function (Dep) {

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

        selectBoolFilterList:  [],

        boolFilterData: {},

        noCreateScopeList: ['User', 'Team', 'Role'],

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

            return _.extend({
                idValues: this.model.get(this.idsName),
                idValuesString: ids ? ids.join(',') : '',
                nameHash: nameHash,
                foreignScope: this.foreignScope,
                placeholder: this.options.placeholder || this.translate('Select'),
                valueIsSet: this.model.has(this.idsName),
                createDisabled: this.createDisabled,
                uploadDisabled: this.uploadDisabled
            }, Dep.prototype.data.call(this));
        },

        getSelectFilters: function () {},

        getSelectBoolFilterList: function () {
            return this.selectBoolFilterList;
        },

        getSelectPrimaryFilterName: function () {
            return this.selectPrimaryFilterName;
        },

        getCreateAttributes: function () {},

        setup: function () {
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

            this.listenTo(this.model, 'change:' + this.idsName, function () {
                this.ids = Espo.Utils.clone(this.model.get(this.idsName) || []);
                this.nameHash = Espo.Utils.clone(this.model.get(this.nameHashName) || {});
            }, this);

            this.sortable = this.sortable || this.params.sortable;

            this.iconHtml = this.getHelper().getScopeColorIconHtml(this.foreignScope);

            if (this.mode != 'list') {
                this.addActionHandler('selectLink', function () {
                    self.notify('Loading...');

                    var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select')  || this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode != 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        boolFilterData: this.getBoolFilterData(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        multiple: this.linkMultiple,
                        massRelateEnabled: true,
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        mandatorySelectAttributeList: this.mandatorySelectAttributeList,
                        forceSelectAllAttributes: this.forceSelectAllAttributes
                    }, function (dialog) {
                        dialog.render();
                        self.notify(false);

                        this.listenTo(dialog, 'select', function (models) {
                            if (this.foreignScope !== 'File'){
                                this.clearView('dialog');
                            }
                            if (models.massRelate) {
                                if (models.where.length === 0) {
                                    // force subquery if primary filter "all" is used in modal
                                    models.where = [{asc: true}]
                                }
                                this.addLinkSubQuery(models);
                                return;
                            }
                            if (Object.prototype.toString.call(models) !== '[object Array]') {
                                models = [models];
                            }
                            models.forEach(function (model) {
                                if (typeof model.get !== "undefined") {
                                    const foreignName = self.getMetadata().get(['entityDefs', self.model.urlRoot, 'fields', self.name, 'foreignName']) ?? 'name';
                                    self.addLink(model.id, model.get(foreignName) ?? model.get('name'));
                                } else if (model.name) {
                                    self.addLink(model.id, model.name);
                                } else {
                                    self.addLink(model.id, model.id);
                                }
                            });
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
                            this.addLink(model.id, model.get('name'));
                        }.bind(this));
                    });
                });
            }
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
            var url = this.foreignScope + '?collectionOnly=true&sortBy=name&maxSize=' + this.AUTOCOMPLETE_RESULT_MAX_COUNT,
                boolList = this.getSelectBoolFilterList(),
                where = [];

            if (boolList && Array.isArray(boolList) && boolList.length > 0) {
                url += '&' + $.param({'boolFilterList': boolList});
            }
            var primary = this.getSelectPrimaryFilterName();
            if (primary) {
                url += '&' + $.param({'primaryFilter': primary});
            }

            if (q) {
                let foreignDefs = this.getMetadata().get(['entityDefs', this.foreignScope, 'fields']);

                if (foreignDefs && typeof foreignDefs === 'object' && foreignDefs.name) {
                    where.push({type: 'like', attribute: 'name', value: this.prepareAutocompleteQueryText(q)});
                }
            }

            let additionalWhere = this.getAutocompleteAdditionalWhereConditions() || [];
            if (Array.isArray(additionalWhere) && additionalWhere.length) {
                additionalWhere.forEach(whereClause => {
                    where.push(whereClause);
                })
            }

            if (where.length) {
                url += '&' + $.param({'where': where});
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
            if (this.mode == 'edit' || this.mode == 'search') {
                this.$element = this.$el.find('input.main-element');

                var $element = this.$element;

                if (!this.autocompleteDisabled) {
                    this.$element.autocomplete({
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
                            response.list.forEach(function(item) {
                                list.push({
                                    id: item.id,
                                    name: item.name,
                                    data: item.id,
                                    value: item.name
                                });
                            }, this);
                            return {
                                suggestions: list
                            };
                        }.bind(this),
                        onSelect: function (s) {
                            this.addLink(s.id, s.name);
                            this.$element.val('');
                        }.bind(this)
                    });


                    this.once('render', function () {
                        $element.autocomplete('dispose');
                    }, this);

                    this.once('remove', function () {
                        $element.autocomplete('dispose');
                    }, this);
                }

                $element.on('change', function () {
                    $element.val('');
                });

                this.renderLinks();

                if (this.mode == 'edit') {
                    if (this.sortable) {
                        this.$el.find('.link-container').sortable({
                            stop: function () {
                                this.fetchFromDom();
                                this.trigger('change');
                            }.bind(this)
                        });
                    }
                }

                if (this.mode == 'search') {
                    this.addLinkSubQueryHtml(this.searchData.subQuery);
                    var type = this.$el.find('select.search-type').val();
                    this.handleSearchType(type);
                }
            }
        },

        renderLinks: function () {
            this.ids.forEach(function (id) {
                this.addLinkHtml(id, this.nameHash[id]);
            }, this);
        },

        deleteLinkSubQuery: function () {
            this.deleteLinkSubQueryHtml();
            this.searchData.subQuery = [];
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

        addLinkSubQuery: function (data) {
            if (!this.searchData) {
                return;
            }
            let subQuery = data.where ?? [];
            this.searchData.subQuery = subQuery;
            this.addLinkSubQueryHtml(subQuery);
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

        afterDeleteLink: function (id) {},

        afterAddLink: function (id) {},

        deleteLinkHtml: function (id) {
            this.$el.find('.link-' + id).remove();
        },

        deleteLinkSubQueryHtml: function () {
            this.$el.find('.link-container .link-subquery').remove();
        },

        addLinkSubQueryHtml: function (subQuery) {
            if (!subQuery || subQuery.length === 0){
                return;
            }

            this.deleteLinkSubQueryHtml();

            var $container = this.$el.find('.link-container');
            var $el = $('<div />').addClass('link-subquery').addClass('list-group-item');
            $el.html('(Subquery) &nbsp');
            $el.prepend('<a href="javascript:" class="pull-right" data-action="clearLinkSubQuery"><span class="fas fa-times"></a>');
            $container.append($el);

            return $el;
        },

        addLinkHtml: function (id, name) {
            var $container = this.$el.find('.link-container');
            var $el = $('<div />').addClass('link-' + id).addClass('list-group-item').attr('data-id', id);
            $el.html(Handlebars.Utils.escapeExpression(name || id) + '&nbsp');
            $el.prepend('<a href="javascript:" class="pull-right" data-id="' + id + '" data-action="clearLink"><span class="fas fa-times"></a>');
            $container.append($el);

            return $el;
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
                    return '<span>' + names.join('</span><br/><span>') + '</span>';
                }
                return;
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
            this.$el.find('.link-container').children().each(function(i, li) {
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

        createFilterView(rule, inputName) {
            const scope = this.model.urlRoot;

            this.filterValue = null;
            this.getModelFactory().create(null, model => {
                let operator = rule.$el.find('.rule-operator-container select').val();
                if (operator === 'query_linked_with') {
                    this.createView(inputName, 'views/fields/text', {
                        name: 'value',
                        el: `#${rule.id} .field-container`,
                        model: model,
                        mode: 'edit'
                    }, view => {
                        this.listenTo(view, 'change', () => {
                            this.filterValue = model.get('value');
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });
                        this.renderAfterEl(view, `#${rule.id} .field-container`);
                    });
                } else if (['linked_with', 'not_linked_with'].includes(operator)) {
                    const attribute = this.defs.params.attribute ?? null;
                    this.createView(inputName, 'views/fields/link-multiple', {
                        name: 'value',
                        el: `#${rule.id} .field-container`,
                        model: model,
                        mode: 'edit',
                        foreignScope: attribute ? attribute.entityType : this.getMetadata().get(['entityDefs', scope, 'fields', this.name, 'entity']) || this.getMetadata().get(['entityDefs', scope, 'links', this.name, 'entity'])
                    }, view => {
                        this.listenTo(view, 'change', () => {
                            this.filterValue = model.get('valueIds');
                            rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                        });
                        this.renderAfterEl(view, `#${rule.id} .field-container`);
                    });
                    this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                        model.set('valueIds', rule.value);
                    });
                }
            });
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                operators: [
                    'linked_with',
                    'not_linked_with',
                    'is_not_linked',
                    'is_linked',
                    'query_linked_with'
                ],
                input: (rule, inputName) => {
                    if (!rule || !inputName) {
                        return '';
                    }

                    this.createFilterView(rule, inputName);
                    this.listenTo(this.model, 'afterUpdateRuleOperator', rule => {
                        this.clearView(inputName);
                        this.createFilterView(rule, inputName);
                    });

                    return `<div class="field-container"></div><input type="hidden" name="${inputName}" />`;
                },
                valueGetter: this.filterValueGetter.bind(this)
            };
        },

    });
});


