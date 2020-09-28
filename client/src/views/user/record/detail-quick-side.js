

Espo.define('views/user/record/detail-quick-side', ['views/record/detail-side', 'views/user/record/detail-side'], function (Dep, UserDetailSide) {

    return Dep.extend({

        setupPanels: function () {
            UserDetailSide.prototype.setupPanels.call(this);
        }

    });

});

