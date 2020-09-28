

Espo.define('views/inbound-email/fields/target-user-position', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.translatedOptions = {
                '': '--' + this.translate('All') + '--'
            };

            this.params.options = [''];
            if (this.model.get('targetUserPosition') && this.model.get('teamId')) {
                this.params.options.push(this.model.get('targetUserPosition'));
            }

            this.loadRoleList(function () {
                if (this.mode == 'edit') {
                    if (this.isRendered()) {
                        this.render();
                    }
                }
            }, this);

            this.listenTo(this.model, 'change:teamId', function () {
                this.loadRoleList(function () {
                    this.render();
                }, this);
            }, this);
        },

        loadRoleList: function (callback, context) {
            var teamId = this.model.get('teamId');
            if (!teamId) {
                this.params.options = [''];
            }

            this.getModelFactory().create('Team', function (team) {
                team.id = teamId;

                this.listenToOnce(team, 'sync', function () {
                    this.params.options = team.get('positionList') || [];
                    this.params.options.unshift('');
                    callback.call(context);
                }, this);

                team.fetch();
            }, this);

        },

    });
});
