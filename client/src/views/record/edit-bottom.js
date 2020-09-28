

Espo.define('views/record/edit-bottom', 'views/record/detail-bottom', function (Dep) {

    return Dep.extend({

        mode: 'edit',

        streamPanel: false,

        relationshipPanels: false,

    });
});


