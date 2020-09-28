

Espo.define('views/user/fields/teams', 'views/fields/link-multiple-with-role', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.roleListMap = {};

            this.loadRoleList(function () {
                if (this.mode == 'edit') {
                    if (this.isRendered() || this.isBeingRendered()) {
                        this.reRender();
                    }
                }
            }, this);


            this.listenTo(this.model, 'change:teamsIds', function () {
                var toLoad = false;
                this.ids.forEach(function (id) {
                    if (!(id in this.roleListMap)) {
                        toLoad = true;
                    }
                }, this);
                if (toLoad) {
                    this.loadRoleList(function () {
                        this.reRender();
                    }, this);
                }
            }, this);
        },

        loadRoleList: function (callback, context) {
            if (!this.getAcl().checkScope('Team', 'read')) return;

            var ids = this.ids || [];
            if (ids.length == 0) return;

            this.getCollectionFactory().create('Team', function (teams) {
                teams.maxSize = 50;
                teams.where = [
                    {
                        type: 'in',
                        field: 'id',
                        value: ids
                    }
                ];

                this.listenToOnce(teams, 'sync', function () {
                    teams.models.forEach(function (model) {
                        this.roleListMap[model.id] = model.get('positionList') || [];
                    }, this);

                    callback.call(context);
                }, this);

                teams.fetch();
            }, this);

        },

        getDetailLinkHtml: function (id, name) {
            name = name || this.nameHash[id];

            var role = (this.columns[id] || {})[this.columnName] || '';
            var roleHtml = '';
            if (role != '') {
                roleHtml = '<span class="text-muted small"> &#187; ' + role + '</span>';
            }
            var lineHtml = '<div>' + '<a href="#' + this.foreignScope + '/view/' + id + '">' + name + '</a> ' + roleHtml + '</div>';
            return lineHtml;
        },

        getJQSelect: function (id, roleValue) {


            var roleList = Espo.Utils.clone((this.roleListMap[id] || []));

            if (!roleList.length) {
                return;
            };


            if (roleList.length || roleValue) {
                $role = $('<select class="role form-control input-sm pull-right" data-id="'+id+'">');

                roleList.unshift('');
                roleList.forEach(function (role) {
                    var selectedHtml = (role == roleValue) ? 'selected': '';
                    var label = role;
                    if (role == '') {
                        label = '--' + this.translate('None', 'labels') + '--';
                    }
                    option = '<option value="'+role+'" ' + selectedHtml + '>' + label + '</option>';
                    $role.append(option);
                }, this);
                return $role;
            } else {
                return $('<div class="small pull-right text-muted">').html(roleValue);
            }
        }

    });

});
