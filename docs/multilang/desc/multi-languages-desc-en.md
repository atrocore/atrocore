The "Multi-Languages" module enables you to store your data in more than one language by adding to your system as many locales as needed. It also allows you to make fields of the `Boolean`, `Enum`, `Multi-Enum`, `Text`, `Varchar`, or `Wysiwyg` types multilingual for any entity in the system and assign user roles different read/edit permissions to these multilingual fields separately for each locale.

## Installation Guide 

The "Multi-Languages" module is automatically installed with [TreoCore](https://treopim.com/help/what-is-treocore), but if you do not have it, then go to `Administration > Module Manager`, find this module in the "Store" list and click `Install`:

![Multilang install](_assets/multilang-install.jpg)

Select the desired version in the installation pop-up window that appears and click the `Install` button. The module background will turn green and it will be moved to the "Installed" section of the Module Manager. Click `Run update` to confirm its installation.

> Please, note that running the system update will lead to the logout of all users.

To update/remove the "Multi-Languages" module from the system, use the corresponding options from its single record actions drop-down list in `Administration > Module Manager`.

Moreover, the "Multi-Languages" module is also installed together with [TreoPIM](https://treopim.com/help/what-is-treopim) and it is TreoPIM dependent, i.e. if TreoPIM is still installed in the system, the "Multi-Languages" module cannot be removed.

## Administrator Functions 

### Module Configuration 

To configure the multi-languages settings, go to `Administration > Multi-Languages`:

![Default settings](_assets/default-settings.jpg)

By default, the `Is active` checkbox and the `Input Language List` field are deactivated, as shown on the screenshot above.

To enable the function for inputting the field values in multiple languages, select the `Is active` checkbox and choose the desired languages from the drop-down list that appears once you click the `Input language list` field:

![Multilang configured](_assets/multilang-configured.jpg)

To change the languages for which the multilingual fields must be filled (e.g. delete the previously defined locales, add new ones), also use the `Input Language List` setting.

When turning off a certain language or completely removing the "Multi-Languages" module, the input field and its value will be removed both from the database and system interface. You will be notified about it with the following warning message:

![Warning](_assets/warning.jpg)

If this language is turned on again or the module is re-installed, the input fields will be restored to the system interface, but with no data in them. So please, be careful with these actions.

On the same "Multi-Languages" module configuration page, you can automatically update the layouts for all entities to include locale fields on the ones where the main multilingual field is already displayed. To do this, click the `Update Layouts` button and confirm your decision in the pop-up that appears:

![Update layouts](_assets/update-layouts.jpg)

Once the action is applied, the missing locale fields are added at the bottom of the entity records overview. To customize the field order display, go to the [Layout Manager](#multilingual-field-display-on-the-layout) and make the desired changes for each entity separately. Moreover, you can configure each layout for each multilingual field separately as described [below](#multilingual-field-display-on-the-layout).

### Multilingual Field Creation 

Currently the following field types can be made multilingual in the TreoPIM system:

| **Field Type** | **Description**                                           |
|----------------|-----------------------------------------------------------|
| Boolean        | Checkbox for the product attribute that can be added for each active locale                                                                   |
| Enum           | Field type for storing drop-down list values for each active locale with the ability to select only one of the variants                        |
| Multi-Enum     | Field type for storing drop-down list values for each active locale with the ability to select one or more variants                            |
| Text           | Field type for storing long text values in multiple languages    |
| Varchar        | Field type for storing short text values (up to 255 characters) in multiple languages                                                         |
| Wysiwyg        | Field type for storing long multiline texts in multiple languages, which contains separate built-in text editors for each active locale |

To create a field that can be made multilingual, go to `Administration > Entity Manager` and click `Fields` for the desired entity:

![Entity manager](_assets/entity-mngr-fields.jpg)

In the new window that opens, all fields of the selected entity are displayed. Click the `Add Field` button, select one of the field types that can be made multilingual:

![Multilang fields selection](_assets/multilang-fields-select.jpg)

On the entity field creation page that opens, specify all necessary parameters for this field and select the `Multi-Language` checkbox to enable automatic creation of multilingual fields via cloning the given main field:

![Multilang field creation](_assets/multilang-field-creation.jpg)

As a result, several entity field records will be created – the main one and locale fields in as many languages as there are activated on the ["Multi-Languages Settings"](#module-configuration) page:

![Entity fields](_assets/entity-fields.jpg)

Names and labels of multilingual fields include names of their locales: "en_US", "de_DE", etc.

> If your system is already integrated with an external system, and you make a simple field multilingual (i.e. set a `Multi-Language` checkbox for it), you may need to change the mapping to ensure correct work with the external systems.

### Multilingual Fields Editing

To edit a multilingual field, either main or locale, click its name on the entity fields list view page and make necessary changes on the page that opens:

![Field editing](_assets/field-editing.jpg)

By default, the locale fields inherit all settings from their main multilingual field, i.e. if the `Audited` checkbox is selected in the main multilingual field on its creation and/or editing, it is automatically selected for the locale fields. However, once a locale field is edited, it loses its inheritance and is assigned its individual value. To discard all changes in the locale fields and return the values of the main multilingual field, use the `Reset to default` button on the locale field detail view page:

![Reset to default](_assets/reset-to-default.jpg)

Please, note if the main multilingual field is mandatory, so are its all locale fields and the `Required` checkbox disappears from the locale field detail view page:

![ML fields required](_assets/ml-fields-required.jpg)

Moreover, input of values in the given multilingual fields is also required for all languages activated in the TreoCore system. Learn more about TreoCore and its advantages [here](https://treopim.com/help/what-is-treocore).

The	`DYNAMIC LOGIC` panel settings are not inherited – it is configured separately for each multilingual field:

![Dynamic logic](_assets/dynamic-logic.jpg)

### Multilingual Field Display on the Layout

To display the newly created multilingual field(s), go to `Administration > Layout Manager` and click the desired entity in the list to unfold the list of layouts available for this entity. Click the layout you wish to configure (e.g. `List`) and enable the created field by its drag-and-drop from the right column to the left:

![Layout Manager](_assets/layout-mngr-multilang.jpg)

Please, note that adding the main multilingual field to the layout does not lead to automatic adding of its locale fields – each field is added separately for each layout type.  

Click `Save` to complete the operation. The added field will be displayed on the configured layout type for the given entity:

![Added fields](_assets/added-fields.jpg)

To customize the fields display for other layout types of the entity, make similar changes to the desired layout types in the Layout Manager, as described above. 

#### Search Filters

In the same way, multilingual fields can also be added to the [search filters](https://treopim.com/help/search-and-filtering) list in the Layout Manager:

![Search filters](_assets/search-filters.jpg)

#### Mass Update

To activate the [mass update](https://treopim.com/help/views-and-panels#mass-actions) of the entity records by multilingual fields, click `Mass Update` and drag-and-drop the desired fields one by one to the `Enabled` column:

![Mass update](_assets/mass-update.jpg)

Please, note that mass update for multilingual `Enum` / `Multi-Enum` fields is performed on the basis of their main field values, and the corresponding values in their locale fields  are updated automatically.

### Multilingual Fields Removal

To remove the entity field with the activated `Multi-Language` checkbox, click `Remove` on the entity fields list view page and confirm your decision in the pop-up that appears:

![Field removal](_assets/ml-field-remove.jpg)

Please, note that locale fields cannot be removed apart from their main multilingual field. To do this, you need to either remove the selection of the `Multi-Language` checkbox on the main field editing page or remove the main multilingual field from the system as it is described above. 

> Please, note that if the "Multi-Languages" module is deactivated and/or removed from the system, *main* multilingual fields for the configured entities remain together with their values, but their *locale* fields and values disappear. Also, these fields lose their multilingual character (the `Multi-Language` checkbox is removed). When the module is activated again, all the locale multilingual fields and their values are restored. However, re-installing the module leads to restoring only locale fields, but not their values. The exception is multilingual fields of the`Varchar` type – their values are also restored for locale fields.
 
### Access Rights

One of the advantages of the "Multi-Languages" module is that it supports the ability to grant separate roles *different* read/edit permissions to multilingual fields. To do this, go to `Administration > Roles > 'Role name'` and on the role detail view page click the `Edit` button:

![Role editing](_assets/role-edit.jpg)  

On the `FIELD LEVEL` panel of the role edit view page that opens, find the entity you wish to configure, click `+` next to it and in the pop-up that appears click the multilingual field to be used as a filter for the given entity:

![Adding filter](_assets/adding-filter.jpg)

Please, note that you can add as many fields as needed, selecting them one by one.

For the added multilingual fields configure read/edit rights via the corresponding drop-down lists:

![Read/edit rights](_assets/read-edit-rights.jpg)

Use `-` to remove the unnecessary field(s).

## User Functions

Once the "Multi-Languages" module is installed and configured by the [administrator](#administrator-functions), user can work with multilingual fields in accordance with his role rights that are predefined by the administrator.

The possible values of the `Enum` and `Multi-Enum` fields are specified for each language by the [administrator](#administrator-functions). For the `Enum` fields the default values, assigned by the administrator, are displayed, and for the `Multi-Enum` fields, users can select the desired options from the existing values:

![ML enum, multi-enum](_assets/ml-enum-multienum.jpg)

Please, note that colorful highlights of the `Enum` and `Multi-Enum` field values are enabled by the ["Colored Fields"](https://treopim.com/store/colored-fields) module.  

For the fields of the `Text`, `Varchar`, and `Wysiwyg` types, the additional input fields are displayed in accordance with the [layout configuration](#multilingual-field-display-on-the-layout) for all languages that have been activated in the [module settings](#module-configuration):  

![ML text, varchar, wysiwyg](_assets/ml-text-varchar-wysiwyg.jpg)

### Multilingual Attributes

In addition to operating with multilingual fields, installing TreoPIM to your system will allow you to make [attributes](https://treopim.com/help/attributes) of the `Boolean`, `Enum`, `Multi-Enum`, `Text`, `Varchar`, and `Wysiwyg` type multilingual – there is the `Multi-Language` checkbox on their detail view pages:

![ML attribute](_assets/ml-attribute.jpg)

To create a multilingual attribute, select the `Multi-Language` checkbox and fill in the required multilingual fields for all active locales:

![ML boolean](_assets/ml-boolean.jpg)

When a multilingual attribute is linked to a [product](https://treopim.com/help/products#product-attributes) record, the value of its main locale is displayed on the `PRODUCT ATTRIBUTES` panel within the product detail view page:

![Product attribute ML](_assets/product-attribute-ml.jpg)

To edit the given attribute's locale values, use the `Edit` option from its single record actions menu and make the desired changes in the pop-up that appears:

![ML attribute editing](_assets/ml-attribute-editing.jpg)

Alternatively, use the `View` option from its single record actions menu and click the `Edit` button in the quick detail pop-up.

### Multilingual Fields and Attributes Filtering

If [TreoPIM](https://treopim.com/help/what-is-treopim) is installed to your system, additional options are added to the `locales` filtering menu on the [product](https://treopim.com/help/search-and-filtering) detail view page:

![Locale filter](_assets/locale-filter.jpg)

Select the desired locale option to filter the product data display accordingly. 

Please, note that once a locale filter is applied, the `PRODUCT ATTRIBUTES` panel contains multilingual attributes of the given locale only.

Leave the `Show Generic Fields` checkbox selected to have the main multilingual field displayed as well or remove its selection to have only locale multilingual fields displayed.

***Install the "Multi-Languages" module now to keep your fields and their values up-to-date in as many languages as needed!***
