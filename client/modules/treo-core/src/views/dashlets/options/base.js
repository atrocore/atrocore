

Espo.define('treo-core:views/dashlets/options/base', 'class-replace!treo-core:views/dashlets/options/base',
    Dep => Dep.extend({

        prepareLayoutAfterConverting(layout) {
            return layout;
        }

    })
);


