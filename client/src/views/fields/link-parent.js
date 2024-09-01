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

Espo.define('views/fields/link-parent', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'linkParent',

        listTemplate: 'fields/link/list',

        detailTemplate: 'fields/link-parent/detail',

        editTemplate: 'fields/link-parent/edit',

        searchTemplate: 'fields/link-parent/search',

        listLinkTemplate: 'fields/link-parent/list-link',

        nameName: null,

        idName: null,

        foreignScopeList: null,

        AUTOCOMPLETE_RESULT_MAX_COUNT: 7,

        autocompleteDisabled: false,

        selectRecordsView: 'views/modals/select-records',

        createDisabled: false,

        noCreateScopeList: ['User', 'Team', 'Role'],

        searchTypeList: ['is', 'isEmpty', 'isNotEmpty'],

        data: function () {
            var nameValue = this.model.get(this.nameName) ? this.model.get(this.nameName) : this.model.get(this.idName);
            if (!nameValue && this.model.get(this.idName) && this.model.get(this.typeName)) {
                nameValue = this.translate(this.model.get(this.typeName), 'scopeNames');
            }
            var iconHtml = null;
            if (this.mode === 'detail' && this.foreignScope) {
                iconHtml = this.getHelper().getScopeColorIconHtml(this.foreignScope);
            }
            return _.extend({
                idName: this.idName,
                nameName: this.nameName,
                typeName: this.typeName,
                idValue: this.model.get(this.idName),
                nameValue: nameValue,
                typeValue: this.model.get(this.typeName),
                foreignScope: this.foreignScope,
                foreignScopeList: this.foreignScopeList,
                valueIsSet: this.model.has(this.idName) || this.model.has(this.typeName),
                iconHtml: iconHtml,
                createDisabled: this.createDisabled
            }, Dep.prototype.data.call(this));
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
            this.nameName = this.name + 'Name';
            this.typeName = this.name + 'Type';
            this.idName = this.name + 'Id';

            this.foreignScopeList = this.options.foreignScopeList || this.foreignScopeList;
            this.foreignScopeList = this.foreignScopeList || this.params.entityList || this.model.getLinkParam(this.name, 'entityList') || [];
            this.foreignScopeList = Espo.Utils.clone(this.foreignScopeList).filter(function (item) {
                if (!this.getMetadata().get(['scopes', item, 'disabled'])) return true;
            }, this);

            this.foreignScope = this.model.get(this.typeName) || this.foreignScopeList[0];

            this.listenTo(this.model, 'change:' + this.typeName, function () {
                this.foreignScope = this.model.get(this.typeName) || this.foreignScopeList[0];
            }.bind(this));

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

            var self = this;

            if (this.mode != 'list') {
                this.addActionHandler('selectLink', function () {
                    this.notify('Loading...');

                    var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode != 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        mandatorySelectAttributeList: this.getMandatorySelectAttributeList(),
                        forceSelectAllAttributes: this.isForceSelectAllAttributes()
                    }, function (dialog) {
                        dialog.render();
                        Espo.Ui.notify(false);
                        this.listenToOnce(dialog, 'select', function (model) {
                            this.clearView('dialog');
                            this.select(model);
                        }, this);
                    }, this);
                });
                this.addActionHandler('clearLink', function () {
                    this.$elementName.val('');
                    this.$elementId.val('');
                    this.trigger('change');
                });

                this.events['change select[name="' + this.typeName + '"]'] = function (e) {
                    this.foreignScope = e.currentTarget.value;
                    this.$elementName.val('');
                    this.$elementId.val('');
                }

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
                            this.select(model)
                        }.bind(this));
                    });
                });
            }
        },

        setupSearch: function () {

            this.events = _.extend({
                'change select.search-type': function (e) {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        handleSearchType: function (type) {
            if (~['is'].indexOf(type)) {
                this.$el.find('div.primary').removeClass('hidden');
            } else {
                this.$el.find('div.primary').addClass('hidden');
            }
        },

        select: function (model) {
            this.$elementName.val(model.get('name'));
            this.$elementId.val(model.get('id'));
            this.trigger('change');
        },

        getMandatorySelectAttributeList: function () {
            this.mandatorySelectAttributeList;
        },

        isForceSelectAllAttributes: function () {
            this.forceSelectAllAttributes;
        },

        getAutocompleteUrl: function () {
            var url = this.foreignScope + '?collectionOnly=true&sortBy=name&maxSize=' + this.AUTOCOMPLETE_RESULT_MAX_COUNT,
                boolList = this.getSelectBoolFilterList();

            if (boolList && Array.isArray(boolList) && boolList.length > 0) {
                url += '&' + $.param({'boolFilterList': boolList});
            }
            var primary = this.getSelectPrimaryFilterName();
            if (primary) {
                url += '&' + $.param({'primaryFilter': primary});
            }
            return url;
        },

        afterRender: function () {
            if (this.mode == 'edit' || this.mode == 'search') {
                this.$elementId = this.$el.find('input[name="' + this.idName + '"]');
                this.$elementName = this.$el.find('input[name="' + this.nameName + '"]');
                this.$elementType = this.$el.find('select[name="' + this.typeName + '"]');

                this.$elementName.on('change', function () {
                    if (this.$elementName.val() == '') {
                        this.$elementName.val('');
                        this.$elementId.val('');
                        this.trigger('change');
                    }
                }.bind(this));

                this.$elementType.on('change', function () {
                    this.$elementName.val('');
                    this.$elementId.val('');
                    this.trigger('change');
                }.bind(this));

                if (this.mode == 'edit') {
                    this.$elementName.on('blur', function (e) {
                        if (this.model.has(this.nameName)) {
                            e.currentTarget.value = this.model.get(this.nameName);
                        }
                    }.bind(this));
                } else if (this.mode == 'search') {
                    this.$elementName.on('blur', function (e) {
                        e.currentTarget.value = '';
                    }.bind(this));
                }

                if (!this.autocompleteDisabled) {
                    this.$elementName.autocomplete({
                        serviceUrl: function (q) {
                            return this.getAutocompleteUrl(q);
                        }.bind(this),
                        minChars: 1,
                        paramName: 'q',
                        formatResult: function (suggestion) {
                            return Handlebars.Utils.escapeExpression(suggestion.name);
                        },
                        transformResult: function (response) {
                            var response = JSON.parse(response);
                            var list = [];
                            response.list.forEach(function (item) {
                                list.push({
                                    id: item.id,
                                    name: item.name,
                                    data: item.id,
                                    value: item.name,
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
                }

                var $elementName = this.$elementName;

                $elementName.on('change', function () {
                    if (!this.model.get(this.idName)) {
                        $elementName.val(this.model.get(this.nameName));
                    }
                }.bind(this));

                this.once('render', function () {
                    $elementName.autocomplete('dispose');
                }, this);

                this.once('remove', function () {
                    $elementName.autocomplete('dispose');
                }, this);
            }

            if (this.mode == 'search') {
                var type = this.$el.find('select.search-type').val();
                this.handleSearchType(type);
            }

            if(this.mode !== 'list' && !this.foreignScopeList.length) {
                this.hide();
            }
        },

        getValueForDisplay: function () {
            return this.model.get(this.nameName);
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.idName) == null || !this.model.get(this.typeName)) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        fetch: function () {
            var data = {};
            data[this.typeName] = this.$elementType.val() || null;
            data[this.nameName] = this.$elementName.val() || null;
            data[this.idName] = this.$elementId.val() || null;
            if (data[this.idName] === null) {
                data[this.typeName] = null;
            }
            return data;
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();

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
            }


            var entityType = this.$elementType.val();
            var entityName = this.$elementName.val()
            var entityId = this.$elementId.val();

            if (!entityType) {
                return false;
            }

            var data;
            if (entityId) {
                data = {
                    type: 'and',
                    field: this.idName,

                    value: [
                        {
                            type: 'equals',
                            field: this.idName,
                            value: entityId,
                        },
                        {
                            type: 'equals',
                            field: this.typeName,
                            value: entityType,
                        }
                    ],
                    valueId: entityId,
                    valueName: entityName,
                    valueType: entityType,
                    data: {
                        type: 'is'
                    }
                };
            } else {
                data = {
                    type: 'and',
                    field: this.idName,
                    value: [
                        {
                            type: 'isNotNull',
                            field: this.idName
                        },
                        {
                            type: 'equals',
                            field: this.typeName,
                            value: entityType,
                        }
                    ],
                    valueType: entityType,
                    data: {
                        type: 'is'
                    }
                };
            }
            return data;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront;
        }
    });
});


