

Espo.define('controllers/last-viewed', 'controllers/record', function (Dep) {

    return Dep.extend({

        entityType: 'ActionHistoryRecord'

    });
});
