<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot0Dot17 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-21 15:00:00');
    }

    public function up(): void
    {
        $this->createDefaultAttributePanel();

        if ($this->isPgSQL()) {
            //CREATE TABLE attribute_group (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', name_de_de VARCHAR(255) DEFAULT NULL, name_uk_ua VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, description_de_de TEXT DEFAULT NULL, description_uk_ua TEXT DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT 'true' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sort_order INT DEFAULT 0, entity_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX UNIQ_8EF8A77377153098EB3B4E33 ON attribute_group (code, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_GROUP_CREATED_BY_ID ON attribute_group (created_by_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_GROUP_MODIFIED_BY_ID ON attribute_group (modified_by_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_GROUP_OWNER_USER_ID ON attribute_group (owner_user_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_GROUP_ASSIGNED_USER_ID ON attribute_group (assigned_user_id, deleted);
            //CREATE TABLE classification (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', name_de_de VARCHAR(255) DEFAULT NULL, name_uk_ua VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, description_de_de TEXT DEFAULT NULL, description_uk_ua TEXT DEFAULT NULL, release VARCHAR(255) DEFAULT NULL, synonyms TEXT DEFAULT NULL, synonyms_de_de TEXT DEFAULT NULL, synonyms_uk_ua TEXT DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, type VARCHAR(255) DEFAULT 'general', is_active BOOLEAN DEFAULT 'false' NOT NULL, entity_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, channel_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_CLASSIFICATION_UNIQUE_CLASSIFICATION ON classification (deleted, release, code);
            //CREATE INDEX IDX_CLASSIFICATION_CREATED_BY_ID ON classification (created_by_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_MODIFIED_BY_ID ON classification (modified_by_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_CHANNEL_ID ON classification (channel_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_OWNER_USER_ID ON classification (owner_user_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ASSIGNED_USER_ID ON classification (assigned_user_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_NAME ON classification (name, deleted);
            //COMMENT ON COLUMN classification.synonyms IS '(DC2Type:jsonArray)';
            //COMMENT ON COLUMN classification.synonyms_de_de IS '(DC2Type:jsonArray)';
            //COMMENT ON COLUMN classification.synonyms_uk_ua IS '(DC2Type:jsonArray)';
            //CREATE TABLE classification_attribute (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', is_required BOOLEAN DEFAULT 'false' NOT NULL, data TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_CLASSIFICATION_ATTRIBUTE_UNIQUE_RELATIONSHIP ON classification_attribute (deleted, classification_id, attribute_id);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_CLASSIFICATION_ID ON classification_attribute (classification_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_ATTRIBUTE_ID ON classification_attribute (attribute_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_CREATED_BY_ID ON classification_attribute (created_by_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_MODIFIED_BY_ID ON classification_attribute (modified_by_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_OWNER_USER_ID ON classification_attribute (owner_user_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_ASSIGNED_USER_ID ON classification_attribute (assigned_user_id, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_CREATED_AT ON classification_attribute (created_at, deleted);
            //CREATE INDEX IDX_CLASSIFICATION_ATTRIBUTE_MODIFIED_AT ON classification_attribute (modified_at, deleted);
            //COMMENT ON COLUMN classification_attribute.data IS '(DC2Type:jsonObject)';
            //CREATE TABLE role_scope_attribute (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', read_action BOOLEAN DEFAULT 'false' NOT NULL, edit_action BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_UNIQUE ON role_scope_attribute (deleted, attribute_id, role_scope_id);
            //CREATE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_ATTRIBUTE_ID ON role_scope_attribute (attribute_id, deleted);
            //CREATE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_ROLE_SCOPE_ID ON role_scope_attribute (role_scope_id, deleted);
            //CREATE TABLE role_scope_attribute_panel (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', read_action BOOLEAN DEFAULT 'false' NOT NULL, edit_action BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, attribute_panel_id VARCHAR(36) DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_PANEL_UNIQUE ON role_scope_attribute_panel (deleted, attribute_panel_id, role_scope_id);
            //CREATE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_PANEL_ROLE_SCOPE_ID ON role_scope_attribute_panel (role_scope_id, deleted);
            //CREATE TABLE brand_attribute_value (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', bool_value BOOLEAN DEFAULT NULL, date_value DATE DEFAULT NULL, datetime_value TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, int_value INT DEFAULT NULL, int_value1 INT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, float_value1 DOUBLE PRECISION DEFAULT NULL, varchar_value VARCHAR(255) DEFAULT NULL, varchar_value_de_de VARCHAR(255) DEFAULT NULL, varchar_value_uk_ua VARCHAR(255) DEFAULT NULL, text_value TEXT DEFAULT NULL, text_value_de_de TEXT DEFAULT NULL, text_value_uk_ua TEXT DEFAULT NULL, reference_value VARCHAR(255) DEFAULT NULL, json_value TEXT DEFAULT NULL, brand_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_BRAND_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON brand_attribute_value (deleted, brand_id, attribute_id);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_BRAND_ID ON brand_attribute_value (brand_id, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_ATTRIBUTE_ID ON brand_attribute_value (attribute_id, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_BOOL_VALUE ON brand_attribute_value (bool_value, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_DATE_VALUE ON brand_attribute_value (date_value, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_DATETIME_VALUE ON brand_attribute_value (datetime_value, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_INT_VALUE ON brand_attribute_value (int_value, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_INT_VALUE1 ON brand_attribute_value (int_value1, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_FLOAT_VALUE ON brand_attribute_value (float_value, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_FLOAT_VALUE1 ON brand_attribute_value (float_value1, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_VARCHAR_VALUE ON brand_attribute_value (varchar_value, deleted);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_TEXT_VALUE ON brand_attribute_value (text_value, deleted) WHERE (length(text_value) < 1000);
            //CREATE INDEX IDX_BRAND_ATTRIBUTE_VALUE_REFERENCE_VALUE ON brand_attribute_value (reference_value, deleted);
            //COMMENT ON COLUMN brand_attribute_value.json_value IS '(DC2Type:jsonObject)';
            //CREATE TABLE custom_entity_attribute_value (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', bool_value BOOLEAN DEFAULT NULL, date_value DATE DEFAULT NULL, datetime_value TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, int_value INT DEFAULT NULL, int_value1 INT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, float_value1 DOUBLE PRECISION DEFAULT NULL, varchar_value VARCHAR(255) DEFAULT NULL, varchar_value_de_de VARCHAR(255) DEFAULT NULL, varchar_value_uk_ua VARCHAR(255) DEFAULT NULL, text_value TEXT DEFAULT NULL, text_value_de_de TEXT DEFAULT NULL, text_value_uk_ua TEXT DEFAULT NULL, reference_value VARCHAR(255) DEFAULT NULL, json_value TEXT DEFAULT NULL, custom_entity_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON custom_entity_attribute_value (deleted, custom_entity_id, attribute_id);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_CUSTOM_ENTITY_ID ON custom_entity_attribute_value (custom_entity_id, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_ATTRIBUTE_ID ON custom_entity_attribute_value (attribute_id, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_BOOL_VALUE ON custom_entity_attribute_value (bool_value, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_DATE_VALUE ON custom_entity_attribute_value (date_value, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_DATETIME_VALUE ON custom_entity_attribute_value (datetime_value, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_INT_VALUE ON custom_entity_attribute_value (int_value, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_INT_VALUE1 ON custom_entity_attribute_value (int_value1, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_FLOAT_VALUE ON custom_entity_attribute_value (float_value, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_FLOAT_VALUE1 ON custom_entity_attribute_value (float_value1, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_VARCHAR_VALUE ON custom_entity_attribute_value (varchar_value, deleted);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_TEXT_VALUE ON custom_entity_attribute_value (text_value, deleted) WHERE (length(text_value) < 1000);
            //CREATE INDEX IDX_CUSTOM_ENTITY_ATTRIBUTE_VALUE_REFERENCE_VALUE ON custom_entity_attribute_value (reference_value, deleted);
            //COMMENT ON COLUMN custom_entity_attribute_value.json_value IS '(DC2Type:jsonObject)';
            //CREATE TABLE foo_attribute_value (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', bool_value BOOLEAN DEFAULT NULL, date_value DATE DEFAULT NULL, datetime_value TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, int_value INT DEFAULT NULL, int_value1 INT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, float_value1 DOUBLE PRECISION DEFAULT NULL, varchar_value VARCHAR(255) DEFAULT NULL, varchar_value_de_de VARCHAR(255) DEFAULT NULL, varchar_value_uk_ua VARCHAR(255) DEFAULT NULL, text_value TEXT DEFAULT NULL, text_value_de_de TEXT DEFAULT NULL, text_value_uk_ua TEXT DEFAULT NULL, reference_value VARCHAR(255) DEFAULT NULL, json_value TEXT DEFAULT NULL, foo_id VARCHAR(36) DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_FOO_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON foo_attribute_value (deleted, foo_id, attribute_id);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_FOO_ID ON foo_attribute_value (foo_id, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_ATTRIBUTE_ID ON foo_attribute_value (attribute_id, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_BOOL_VALUE ON foo_attribute_value (bool_value, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_DATE_VALUE ON foo_attribute_value (date_value, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_DATETIME_VALUE ON foo_attribute_value (datetime_value, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_INT_VALUE ON foo_attribute_value (int_value, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_INT_VALUE1 ON foo_attribute_value (int_value1, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_FLOAT_VALUE ON foo_attribute_value (float_value, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_FLOAT_VALUE1 ON foo_attribute_value (float_value1, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_VARCHAR_VALUE ON foo_attribute_value (varchar_value, deleted);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_TEXT_VALUE ON foo_attribute_value (text_value, deleted) WHERE (length(text_value) < 1000);
            //CREATE INDEX IDX_FOO_ATTRIBUTE_VALUE_REFERENCE_VALUE ON foo_attribute_value (reference_value, deleted);
            //COMMENT ON COLUMN foo_attribute_value.json_value IS '(DC2Type:jsonObject)';
            //CREATE TABLE foo_classification (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, classification_id VARCHAR(36) DEFAULT NULL, foo_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));
            //CREATE UNIQUE INDEX IDX_FOO_CLASSIFICATION_UNIQUE_RELATION ON foo_classification (deleted, classification_id, foo_id);
            //CREATE INDEX IDX_FOO_CLASSIFICATION_CREATED_BY_ID ON foo_classification (created_by_id, deleted);
            //CREATE INDEX IDX_FOO_CLASSIFICATION_MODIFIED_BY_ID ON foo_classification (modified_by_id, deleted);
            //CREATE INDEX IDX_FOO_CLASSIFICATION_CLASSIFICATION_ID ON foo_classification (classification_id, deleted);
            //CREATE INDEX IDX_FOO_CLASSIFICATION_FOO_ID ON foo_classification (foo_id, deleted);
            //CREATE INDEX IDX_FOO_CLASSIFICATION_CREATED_AT ON foo_classification (created_at, deleted);
            //CREATE INDEX IDX_FOO_CLASSIFICATION_MODIFIED_AT ON foo_classification (modified_at, deleted);
            //ALTER TABLE attribute ADD name VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE attribute ADD name_de_de VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE attribute ADD name_uk_ua VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE attribute ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
            //ALTER TABLE attribute ADD modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
            //ALTER TABLE attribute ADD code VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE attribute ADD type VARCHAR(255) DEFAULT 'text';
            //ALTER TABLE attribute ADD is_multilang BOOLEAN DEFAULT 'false' NOT NULL;
            //ALTER TABLE attribute ADD pattern VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE attribute ADD prohibited_empty_value BOOLEAN DEFAULT 'false' NOT NULL;
            //ALTER TABLE attribute ADD data TEXT DEFAULT NULL;
            //ALTER TABLE attribute ADD default_unit VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE attribute ADD default_date VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE attribute ADD is_required BOOLEAN DEFAULT 'false' NOT NULL;
            //ALTER TABLE attribute ADD is_read_only BOOLEAN DEFAULT 'false' NOT NULL;
            //ALTER TABLE attribute ADD sort_order INT DEFAULT NULL;
            //ALTER TABLE attribute ADD attribute_group_sort_order INT DEFAULT NULL;
            //ALTER TABLE attribute ADD tooltip TEXT DEFAULT NULL;
            //ALTER TABLE attribute ADD tooltip_de_de TEXT DEFAULT NULL;
            //ALTER TABLE attribute ADD tooltip_uk_ua TEXT DEFAULT NULL;
            //ALTER TABLE attribute ADD description TEXT DEFAULT NULL;
            //ALTER TABLE attribute ADD description_de_de TEXT DEFAULT NULL;
            //ALTER TABLE attribute ADD description_uk_ua TEXT DEFAULT NULL;
            //ALTER TABLE attribute ADD amount_of_digits_after_comma INT DEFAULT NULL;
            //ALTER TABLE attribute ADD use_disabled_textarea_in_view_mode BOOLEAN DEFAULT 'false' NOT NULL;
            //ALTER TABLE attribute ADD not_null BOOLEAN DEFAULT 'false' NOT NULL;
            //ALTER TABLE attribute ADD trim BOOLEAN DEFAULT 'false' NOT NULL;
            //ALTER TABLE attribute ADD created_by_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD modified_by_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD attribute_group_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD attribute_panel_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD extensible_enum_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD entity_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD composite_attribute_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD file_type_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD measure_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD html_sanitizer_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD owner_user_id VARCHAR(36) DEFAULT NULL;
            //ALTER TABLE attribute ADD assigned_user_id VARCHAR(36) DEFAULT NULL;
            //COMMENT ON COLUMN attribute.data IS '(DC2Type:jsonObject)';
            //CREATE UNIQUE INDEX IDX_ATTRIBUTE_UNIQUE_CODE ON attribute (deleted, entity_id, code);
            //CREATE INDEX IDX_ATTRIBUTE_CREATED_BY_ID ON attribute (created_by_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_MODIFIED_BY_ID ON attribute (modified_by_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_ATTRIBUTE_GROUP_ID ON attribute (attribute_group_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_EXTENSIBLE_ENUM_ID ON attribute (extensible_enum_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_COMPOSITE_ATTRIBUTE_ID ON attribute (composite_attribute_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_FILE_TYPE_ID ON attribute (file_type_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_MEASURE_ID ON attribute (measure_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_OWNER_USER_ID ON attribute (owner_user_id, deleted);
            //CREATE INDEX IDX_ATTRIBUTE_ASSIGNED_USER_ID ON attribute (assigned_user_id, deleted);
            //ALTER TABLE role_scope ADD create_attribute_value_action VARCHAR(255) DEFAULT NULL;
            //ALTER TABLE role_scope ADD delete_attribute_value_action VARCHAR(255) DEFAULT NULL
        } else {

        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }

    private function createDefaultAttributePanel(): void
    {
        @mkdir('data/reference-data');

        $result = [];
        if (file_exists('data/reference-data/AttributePanel.json')) {
            $result = @json_decode(file_get_contents('data/reference-data/AttributePanel.json'), true);
            if (!is_array($result)) {
                $result = [];
            }
        }

        $result['attributeValues'] = [
            'id'        => 'attributeValues',
            'code'      => 'attributeValues',
            'name'      => 'Attributes',
            'sortOrder' => 0,
            'entityId'  => 'Product',
            'default'   => true
        ];

        file_put_contents('data/reference-data/AttributePanel.json', json_encode($result));
    }
}
