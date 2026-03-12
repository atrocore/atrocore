---
title: Pricing
taxonomy:
    category: docs
---

The "Pricing" module enables to operate with different prices for different customer groups and channels. In addition, you can take usage of scale pricing capabilities and automatic price recalculation for different currencies. The module enables you also to set the price in each currency for any amount of products manually.

You can create multiple price profiles to be able to set different prices. Examples of price profiles may be the following: Regular prices, Wholesale prices, B2B prices, VIP clients prices, Seasonal discounts, etc.

## Administrator Functions

The "Pricing" module significantly extends the pricing functionality of the AtroPIM PIM System.

After the module installation, a new entity "Price Profile" is added to the system. It is also automatically added to your [Navigation Menu](../../01.atrocore/03.administration/13.user-interface/01.navigation/). New "Prices" panel is added to the [Products](../../05.pim/03.products/) detail view page.

### Currency Configuration

If you want to enable working with multiple currencies you need to configure these in the administration, go to `Administration > Currency`:

![Currency settings](_assets/currencies.png){.large}

The `EUR` currency is set by default, but you can expand the list with as many currencies as needed. To do this, click the `Currency List` field and choose the desired options from the drop-down list that appears.

Here you can also select the default currency (to be used when creating new records) and currency format via the corresponding drop-down lists. In the `Currency Decimal Places` field, specify the number of decimal places to be used in currency fields and calculations or leave the field empty to have all filled decimal places displayed.

On the "Currency Rates" panel you should also specify the conversion rates for the currencies available in the currency list if you want to use automatic price conversion. For this, select the base currency and enter its rate value according to other currencies.

Click the `Save` button to apply your currency configuration.


### Access Rights

By default, the pricing feature is enabled for all users, however, it can be disabled for certain user roles, if needed, on the `Administration > Roles > 'Role name'` page:

![Access rights](_assets/pricing-role-cfg.jpg){.large}

Please, note that access rights for `Channels` and `Products` should also be enabled.

## User Functions

After the "Pricing" module is installed and configured by the administrator, user can work with price profiles in accordance with his role rights that are predefined by the administrator.

## Price Profile

A price profile is a configurable price variation that allows you to define various prices for different groups of customers, both in numbers and currencies.

![price profiles](_assets/price-profiles.png){.large}

### Creating

To create a new price profile record, click `Price Profiles` in the navigation menu to get to the profiles [list view](../../01.atrocore/04.understanding-ui/docs.md#list-view), and then click the `Create Price Profile` button. The common creation window will open:

![price-profile-create](_assets/price-profiles-create.png){.large}

Here enter the desired name for the price profile record being created and define the currencies to be used in it (in accordance with the ones defined on the [currency configuration](#currency-configuration) step).

Please, note that the default currency cannot be removed.

Activate the price profile and enter its description, if needed.

Please, note that only activated profiles are added to the products available in the system.

Click the `Save` button to finish the price profile creation and move to its [configuration](#currency-configuration) or `Cancel` to abort the process.

### Assigning Price Profiles to Channels

After the new price profile is saved on the panel "Channels" you can select the channels, for which this price profile should be valid.

![price-profile-example](_assets/price-profiles-example.png){.large}

Alternatively, you can link price profiles with channels on the "Price Profiles" panel within the desired channel detail view page:

![Price profiles panel](_assets/channels.png){.large}

Thus, one Price Profile can be assigned to multiple Channels, and one Channel may have multiple Price Profiles assigned to it.

## Setting Product Prices

As soon as the active Price Profile is created, it is added to all products available in the system and is displayed on the "Prices" panel:

![prices](_assets/prices.png){.large}

Here you can define product prices for all currencies pre-configured for the given price profile. To add a new price entry click on the plus icon in the top right corner of the panel.

![prices](_assets/prices-add.png){.large}

A popup window appears, in which you can set the desired minimum amount, select the respective price profile, currency and the price.

![prices](_assets/prices-add-popup.png){.large}

If a non-main currency is selected and a price entry for the current amount in the main currency exists, option "Calculate automatically" appears additionally. If set the price for the current currency will be calculated automatically. In the future if the price in the main currency for the current amount is changed the prices in this currency will be automatically recalculated. For such records "Base price" is set and is displayed on the "Prices" panel, so you can easily recognize, which prices will be recalculated automatically.

To edit some price entry select `Edit` from the record menu:

![prices](_assets/price-edit.png){.large}

You can select `View` to see in the side popup all the information about a certain price entry.

![prices](_assets/price-view.png){.large}

To remove a price entry, click on the option `Remove`.

## How Twig Fields Should be used for Price Calculation

When using the Twig type field, you have full flexibility in defining your calculation conditions or your calculation formula in the "Calculation Profile," as well as your minimum or maximum price validation on the "Price Profile" page.

On Price Profile page, pay attention to minimum and maximum price validation fields.

![prices](_assets/price-profile-add.png){.large}

On Calculation Profile page, pay attention to calculation conditions and formulae fields.

![prices](_assets/calculation-profile-add.png){.large}


### For Calculation Conditions and Formulae in "calculation profile":
When you choose the Twig type field for a calculation condition or formula in the Calculation Profile, you'll see something like the following by default:

    {% set proceed = true %}
    {% set calculatedPrice = productPrice.price %}

The proceed variable determines whether the condition is met, and the calculatedPrice variable determines the resulting price. You should not remove these variables, as doing so will distort the results. Instead, you can change their values as needed.
In calculation profile, you can use config data, it can be used in your calculation profile twig.

![prices](_assets/calculation-parameter-add.png){.large}

To use config data in the twig fields, you just need to call config, then precise what you want.
For example, if i want the baseCurrency, i can write config.baseCurrency

To use your currency rate in the twig fields, you just need to call the currency you want like the parameter of the object currencyRate; let's assume that you have currency EUR, USD in your system, so you can call : currencyRate.EUR, currencyRate.USD; if the currency does not exist, it will return 0.

to get one of value, i simply write calculationParameter.germanTaxes for german taxes; calculationParameter.retailMargin for retail margin,
calculationParameter.shippingRates;

#### Example Calculation Conditions
Here are some examples of how you can define your calculation conditions using Twig:

Example 1: Affecting a True or False Value

    {% set proceed = false %}
    {{ proceed }}

This condition will never be met, so the formula will never be applied.

Example 2: Dynamic Value

    {% set proceed = (product.brand in ['epson', 'microsoft', 'apple']) %}
    {{ proceed }}

The proceed value will be dynamic; it will be true if the brand of the current product is one of those three, and false otherwise.

Example 3: With Config Data

    {% if product.tax >= 19 and config.baseCurrency == 'EUR' %}
    {% set proceed = true %}
    {% else %}
    {% set proceed = false %}
    {% endif %}
    {{ proceed }}

In this example, we check if the product.tax is more or equal than 19 , also if our baseCurrency coming from config data is EUR , then we set proceed to true

Example 4: With currency rate

    {% if currencyRate.EUR*productPrice.price > 100 %}
    {% set proceed = true %}
    {% else %}
    {% set proceed = false %}
    {% endif %}
    {{ proceed }}

In this example, we check if the EURO currency rate multiply by the price of the productPrice is more than 100

Example 5: Complex Condition

    {% if product.tax >= 10 and product.tax <= 25 %}
    {% set proceed = true %}
    {% elseif productPrice.price < 50 %}
    {% set proceed = false %}
    {% endif %}
    {{ proceed }}

This example defines a condition based on tax and price. If the tax is between 10 and 25, proceed will be set to true. Otherwise, if the price is less than 50, proceed will be set to false.

#### Example Calculation Formulae
Here are some examples of how you can define your calculation formulae using Twig:

Example 1: Taking the Purchase Price as Base

    {% set calculatedPrice = productPrice.price %}
    {{ calculatedPrice }}

This example sets the calculated price equal to the purchase price.

Example 2: Fixed Value

    {% set calculatedPrice = 150 %}
    {{ calculatedPrice }}

This example sets the calculated price to a fixed value of 150.

Example 3: Dynamic Calculated Price

    {% if product.brand is empty %}
    {% set calculatedPrice = 10 + product.tax * productPrice.price * 1.2 %}
    {% elseif product.tax < 30 %}
    {% set calculatedPrice = (1 + product.tax) * productPrice.price * 1.15 %}
    {% elseif 'Computer' in product.categories %}
    {% set calculatedPrice = (1.4 * productPrice.price) | ceil - 2 %}
    {% endif %}
    {{ calculatedPrice }}

In the first case, if the brand is empty, the formula will be 10 + product.tax * productPrice.price * 1.2. In the second case, if the tax is less than 30, we have another formula. In the last case, we round the value of 1.4 * price then subtract 2.

Example 4: Calculated Price from Another PriceProfile

    {% set getProductPrice = getPrice(product.id, 'b2b usd profile', productPrice.amount) %}
    {% set calculatedPrice = getProductPrice.price * 1.2 %}
    {{ calculatedPrice }}

In this example, you can define a price related to a price profile; you can use it through the getPrice function, which needs 3 parameters:
- the id of the product you target(in this case, it is the same product), the name
- the name of the profile you target (in this case it is 'b2b usd profile'), you can copy and paste it in quotes
- the amount of product you target, with these elements, it is going to fetch the product price with all those 3 values
Then it will return a productPrice object, you can then use it to access any fields of element, in the example case, i just access the price field by using getProductPrice.price... then I multiply by 1.2 to have 20% more than the purchase price. At the end my calculatedPrice for the current product price is based on another product price.

When one price is calculated on the basis of another, the other is noted that it has already been listed.

Example 5: Smooth your price

    {% set calculatedPrice = smoothyPrice(productPrice.price, 0.01, 0.5, 'up') %}
    {{ calculatedPrice }}

In this example, you can define a smoothyPrice; smoothyPrice is price like 14.59$, 29.49$, 39.99$; the smoothyPrice has 4 parameters, the first one is the price to round; the second one is the delta value to reduce or add; the third parameter is the multiplier roundTo, it can be 10, 1, 0.5 etc... the last parameter is the rounding direction to tell if we should round price up, down, or normal; default value is normal; See some examples to better understand

- if we have smoothyPrice(12.65, -0.01, 1, 'up') = 12.99; first we round 12.65 according to multiplier 1 and direction 'up', we have 13, then 13 -0.01 = 12.99
- if we have smoothyPrice(12.65, -0.01, 1, 'down') = 11.99; first we round 12.65 according to multiplier 1 and direction 'down', we have 12, then 12 -0.01 = 11.99
- if we have smoothyPrice(12.65, -0.01, 1) = 12.99; first we round 12.65 according to multiplier 1 and direction 'normal', we have 13, then 13 -0.01 = 12.99
- if we have smoothyPrice(12.65, -0.01, 0.5, 'up') = 12.99; first we round 12.65 according to multiplier 0.5 and direction 'up', we have 13, then 13 -0.01 = 12.99
- if we have smoothyPrice(12.65, -0.01, 0.5, 'down') = 12.49; first we round 12.65 according to multiplier 0.5 and direction 'down', we have 12.5, then 12.5 -0.01 = 12.49
- if we have smoothyPrice(12.65, -0.01, 0.5) = 12.49; first we round 12.65 according to multiplier 0.5 and direction 'normal', we have 12.5, then 12.5 -0.01 = 12.49
- if we have smoothyPrice(12.65, -0.01, 10, 'up') = 12.49; first we round 12.65 according to multiplier 10 and direction 'up', we have 20, then 20 - 0.01 = 19.99
- if we have smoothyPrice(12.65, -0.01, 10, 'down') = 12.49; first we round 12.65 according to multiplier 10 and direction 'down', we have 10, then 10 - 0.01 = 9.99
- if we have smoothyPrice(12.65, -0.01, 10, 'normal') = 12.49; first we round 12.65 according to multiplier 10 and direction 'normal', we have 10, then 10 - 0.01 = 9.99
You can use this function at the last line to smooth your final price value;

### For "minimum validation price" or "maximum validation price"
When creating a new "price profile," you will see default validation values. By default, the minimum validation price is set to {% set validationPrice = 0.2 * productPrice.price %}, and the maximum validation price is set to {% set validationPrice = 2 * productPrice.price %}. These values ensure that the calculated price falls within a certain range based on the product price. For example, if the product price is 10, the minimum acceptable price will be 0.2*10 = 2, and the maximum acceptable price will be 2*10 = 20. If the calculated price falls outside of this range when the "recalculatePrice" function is executed, the calculated price will not be set, and the date at which the price validation failed will be recorded according to the minimum acceptable price.

You can also set a fixed value that is not dependent on the product price by setting {% set validationPrice = 10 %}. This means that the minimum/maximum acceptable price for any price will be 10. If a price less than 10 is set, it will fail, and the date will be registered in "Calculated price validation failure." The error "The price calculated on the stated date is not to be set, if it is not between minimum and maximum prices allowed for the price profile." will be shown.

To access the fields of the product and product price, you can use the following syntax:

- productPrice.price: Price amount in the entity Product Price
- productPrice.calculatedPrice: Calculated price amount in the entity Product Price, which is to be recalculated
- productPrice.amount: amount of the product
- productPrice.priceCurrency: currency of the price
- product.Price: Purchase price amount in the entity Product
- product.brand: Brand of the product
- product.tax: Tax of the product
With these elements, you can combine and define your minimum/maximum validation dynamically. Here are some examples:

#### Example for minimum and maximum price validation
Example 1 (taking the purchase price as a base):

    {% set validationPrice = 0.2 * productPrice.price %}
    {{ validationPrice }}

Example 2 (setting a fixed value for price validation):

    {% set validationPrice = 10 %}
    {{ validationPrice }}

Example 3 (dynamic price validation with condition):

    {% if product.brand is empty %}
    {% set validationPrice = 10 + product.tax * productPrice.price %}
    {% elseif product.tax < 20 %}
    {% set validationPrice = (1 + product.tax) * productPrice.price %}
    {% elseif 'Computer' in product.categories %}
    {% set validationPrice = 1.4 * productPrice.price %}
    {% endif %}
    {{ validationPrice }}
