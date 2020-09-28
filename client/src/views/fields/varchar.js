

Espo.define('views/fields/varchar', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'varchar',

        detailTemplate: 'fields/varchar/detail',

        searchTemplate: 'fields/varchar/search',

        searchTypeList: ['startsWith', 'contains', 'equals', 'endsWith', 'like', 'notContains', 'notEquals', 'notLike', 'isEmpty', 'isNotEmpty'],

        setupSearch: function () {
            this.events = _.extend({
                'change select.search-type': function (e) {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            if (
                this.model.get(this.name) !== null
                &&
                this.model.get(this.name) !== ''
                &&
                this.model.has(this.name)
            ) {
                data.isNotEmpty = true;
            }
            data.valueIsSet = this.model.has(this.name);

            if (this.mode === 'search') {
                if (typeof this.searchParams.value === 'string') {
                    this.searchData.value = this.searchParams.value;
                }
            }
            return data;
        },

        handleSearchType: function (type) {
            if (~['isEmpty', 'isNotEmpty'].indexOf(type)) {
                this.$el.find('input.main-element').addClass('hidden');
            } else {
                this.$el.find('input.main-element').removeClass('hidden');
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.mode == 'search') {
                var type = this.$el.find('select.search-type').val();
                this.handleSearchType(type);
            }
        },

        fetch: function () {
            var data = {};
            var value = this.$element.val();
            if (this.params.trim || this.forceTrim) {
                if (typeof value.trim === 'function') {
                    value = value.trim();
                }
            }
            data[this.name] = value;
            return data;
        },

        fetchSearch: function () {
            var type = this.$el.find('[name="'+this.name+'-type"]').val() || 'startsWith';

            var data;

            if (~['isEmpty', 'isNotEmpty'].indexOf(type)) {
                if (type == 'isEmpty') {
                    data = {
                        type: 'or',
                        value: [
                            {
                                type: 'isNull',
                                field: this.name,
                            },
                            {
                                type: 'equals',
                                field: this.name,
                                value: ''
                            }
                        ],
                        data: {
                            type: type
                        }
                    }
                } else {
                    data = {
                        type: 'and',
                        value: [
                            {
                                type: 'notEquals',
                                field: this.name,
                                value: ''
                            },
                            {
                                type: 'isNotNull',
                                field: this.name,
                                value: null
                            }
                        ],
                        data: {
                            type: type
                        }
                    }
                }
                return data;
            } else {
                var value = this.$element.val().toString().trim();
                value = value.trim();
                if (value) {
                    data = {
                        value: value,
                        type: type,
                        data: {
                            type: type
                        }
                    }
                    return data;
                }
            }
            return false;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type;
        }

    });
});

