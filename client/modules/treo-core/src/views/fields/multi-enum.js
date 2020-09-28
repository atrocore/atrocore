

Espo.define('treo-core:views/fields/multi-enum', 'class-replace!treo-core:views/fields/multi-enum',
    Dep => Dep.extend({

        afterRender: function () {
            if (this.mode === 'edit') {
                this.$element = this.$el.find('[name="' + this.name + '"]');

                var data = [];

                var valueList = Espo.Utils.clone(this.selected);
                for (var i in valueList) {
                    var value = valueList[i];
                    if (valueList[i] === '') {
                        valueList[i] = '__emptystring__';
                    }
                    if (!~(this.params.options || []).indexOf(value)) {
                        data.push({
                            value: value,
                            label: value
                        });
                    }
                }
                valueList = valueList.map(item => item.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-'));
                this.$element.val(valueList.join(':,:'));

                (this.params.options || []).forEach(function (value) {
                    var label = this.getLanguage().translateOption(value, this.name, this.scope);
                    if (this.translatedOptions) {
                        if (value in this.translatedOptions) {
                            label = this.translatedOptions[value];
                        }
                    }
                    if (value === '') {
                        value = '__emptystring__';
                    }
                    if (label === '') {
                        label = this.translate('None');
                    }
                    data.push({
                        value: value,
                        label: label
                    });
                }, this);

                data.forEach(item => item.value = item.value.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-'));
                var selectizeOptions = {
                    options: data,
                    delimiter: ':,:',
                    labelField: 'label',
                    valueField: 'value',
                    highlight: false,
                    searchField: ['label'],
                    plugins: ['remove_button', 'drag_drop'],
                    score: function (search) {
                        var score = this.getScoreFunction(search);
                        search = search.toLowerCase();
                        return function (item) {
                            if (item.label.toLowerCase().indexOf(search) === 0) {
                                return score(item);
                            }
                            return 0;
                        };
                    }
                };

                if (!(this.params.options || []).length) {
                    selectizeOptions.persist = false;
                    selectizeOptions.create = function (input) {
                        return {
                            value: input,
                            label: input
                        }
                    };
                    selectizeOptions.render = {
                        option_create: function (data, escape) {
                            return '<div class="create"><strong>' + escape(data.input) + '</strong>&hellip;</div>';
                        }
                    };
                }

                this.$element.selectize(selectizeOptions);

                if (this.$element.size()) {
                    let depPositionDropdown = this.$element[0].selectize.positionDropdown;
                    this.$element[0].selectize.positionDropdown = function () {
                        depPositionDropdown.call(this);

                        this.$dropdown.hide();
                        let pageHeight = $(document).height();
                        this.$dropdown.show();
                        let dropdownHeight = this.$dropdown.outerHeight(true);
                        if (this.$dropdown.offset().top + dropdownHeight > pageHeight) {
                            this.$dropdown.css({
                                'top': `-${dropdownHeight}px`
                            });
                        }
                    };
                }

                this.$element.on('change', function () {
                    this.trigger('change');
                }.bind(this));
            }

            if (this.mode == 'search') {
                this.renderSearch();
            }
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            data[this.name] = data[this.name].map(item => item.replace(/-quote-/g, '"').replace(/-backslash-/g, '\\'));
            return data;
        },

    })
);