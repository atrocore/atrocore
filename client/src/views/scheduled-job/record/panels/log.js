

Espo.define('views/scheduled-job/record/panels/log', 'views/record/panels/relationship', function (Dep) {

    return Dep.extend({

        setupListLayout: function () {
            var jobWithTargetList = this.getMetadata().get(['clientDefs', 'ScheduledJob', 'jobWithTargetList']) || [];
            if (~jobWithTargetList.indexOf(this.model.get('job'))) {
                this.listLayoutName = 'listSmallWithTarget'
            }
        }

    });
});
