
Espo.define('views/account/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        relatedAttributeMap: {
            'contacts': {
                'billingAddressCity': 'addressCity',
                'billingAddressStreet': 'addressStreet',
                'billingAddressPostalCode': 'addressPostalCode',
                'billingAddressState': 'addressState',
                'billingAddressCountry': 'addressCountry',
                'id': 'accountId',
                'name': 'accountName'
            }
        }
    });
});

