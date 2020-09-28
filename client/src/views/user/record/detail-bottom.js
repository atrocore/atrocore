

Espo.define('views/user/record/detail-bottom', 'views/record/detail-bottom', function (Dep) {

    return Dep.extend({

        setupPanels: function () {
            Dep.prototype.setupPanels.call(this);

            var showActivities = this.getAcl().checkUserPermission(this.model);
            if (!showActivities) {
                if (this.getAcl().get('userPermission') === 'team') {
                    if (!this.model.has('teamsIds')) {
                        this.listenToOnce(this.model, 'sync', function () {
                            if (this.getAcl().checkUserPermission(this.model)) {
                                this.showPanel('stream', function () {
                                    this.getView('stream').collection.fetch();
                                });
                            }
                        }, this);
                    }
                }
            }

            this.panelList.push({
                "name":"stream",
                "label":"Stream",
                "view":"views/user/record/panels/stream",
                "sticked": true,
                "hidden": !showActivities
            });
        }

    });

});

