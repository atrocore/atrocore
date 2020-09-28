

Espo.define('views/fields/user', 'views/fields/link', function (Dep) {

    return Dep.extend({

        searchTemplate: 'fields/user/search',

        setupSearch: function () {
            Dep.prototype.setupSearch.call(this);

            this.searchTypeList = Espo.Utils.clone(this.searchTypeList);
            this.searchTypeList.push('isFromTeams');

            this.searchData.teamIdList = this.getSearchParamsData().teamIdList || this.searchParams.teamIdList || [];
            this.searchData.teamNameHash = this.getSearchParamsData().teamNameHash || this.searchParams.teamNameHash || {};

            this.events['click a[data-action="clearLinkTeams"]'] = function (e) {
                var id = $(e.currentTarget).data('id').toString();
                this.deleteLinkTeams(id);
            };

            this.addActionHandler('selectLinkTeams', function () {
                this.notify('Loading...');

                var viewName = this.getMetadata().get('clientDefs.Team.modalViews.select') || 'views/modals/select-records';

                this.createView('dialog', viewName, {
                    scope: 'Team',
                    createButton: false,
                    multiple: true
                }, function (view) {
                    view.render();
                    this.notify(false);
                    this.listenToOnce(view, 'select', function (models) {
                        if (Object.prototype.toString.call(models) !== '[object Array]') {
                            models = [models];
                        }
                        models.forEach(function (model) {
                            this.addLinkTeams(model.id, model.get('name'));
                        }, this);
                    });
                }, this);
            });

            this.events['click a[data-action="clearLinkTeams"]'] = function (e) {
                var id = $(e.currentTarget).data('id').toString();
                this.deleteLinkTeams(id);
            };
        },

        handleSearchType: function (type) {
            Dep.prototype.handleSearchType.call(this, type);

            if (type === 'isFromTeams') {
                this.$el.find('div.teams-container').removeClass('hidden');
            } else {
                this.$el.find('div.teams-container').addClass('hidden');
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode == 'search') {
                var $elemeneTeams = this.$el.find('input.element-teams');
                $elemeneTeams.autocomplete({
                    serviceUrl: function (q) {
                        return 'Team?sortBy=name&maxCount=' + this.AUTOCOMPLETE_RESULT_MAX_COUNT;
                    }.bind(this),
                    minChars: 1,
                    paramName: 'q',
                       formatResult: function (suggestion) {
                        return suggestion.name;
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
                        this.addLinkTeams(s.id, s.name);
                        $elemeneTeams.val('');
                    }.bind(this)
                });


                this.once('render', function () {
                    $elemeneTeams.autocomplete('dispose');
                }, this);

                this.once('remove', function () {
                    $elemeneTeams.autocomplete('dispose');
                }, this);

                var type = this.$el.find('select.search-type').val();

                if (type == 'isFromTeams') {
                    this.searchData.teamIdList.forEach(function (id) {
                        this.addLinkTeamsHtml(id, this.searchData.teamNameHash[id]);
                    }, this);
                }
            }
        },

        deleteLinkTeams: function (id) {
            this.deleteLinkTeamsHtml(id);

            var index = this.searchData.teamIdList.indexOf(id);
            if (index > -1) {
                this.searchData.teamIdList.splice(index, 1);
            }
            delete this.searchData.teamNameHash[id];
        },

        addLinkTeams: function (id, name) {
            this.searchData.teamIdList = this.searchData.teamIdList || [];

            if (!~this.searchData.teamIdList.indexOf(id)) {
                this.searchData.teamIdList.push(id);
                this.searchData.teamNameHash[id] = name;
                this.addLinkTeamsHtml(id, name);
            }
        },

        deleteLinkTeamsHtml: function (id) {
            this.$el.find('.link-teams-container .link-' + id).remove();
        },

        addLinkTeamsHtml: function (id, name) {
            var $container = this.$el.find('.link-teams-container');
            var $el = $('<div />').addClass('link-' + id).addClass('list-group-item');
            $el.html(name + '&nbsp');
            $el.prepend('<a href="javascript:" class="pull-right" data-id="' + id + '" data-action="clearLinkTeams"><span class="fas fa-times"></a>');
            $container.append($el);

            return $el;
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();

            if (type == 'isFromTeams') {
                var data = {
                    type: 'isUserFromTeams',
                    field: this.name,
                    value: this.searchData.teamIdList,
                    data: {
                        type: type,
                        teamIdList: this.searchData.teamIdList,
                        teamNameHash: this.searchData.teamNameHash
                    }
                };
                return data;
            }

            return Dep.prototype.fetchSearch.call(this);
        }

    });
});

