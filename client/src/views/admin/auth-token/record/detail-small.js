

Espo.define('views/admin/auth-token/record/detail-small', 'views/record/detail-small', function (Dep) {

    return Dep.extend({

        sideDisabled: true,

        isWide: true,

        bottomView: 'views/record/detail-bottom'

    });
});

