<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;

class V1Dot10Dot50 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-31 11:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE notification_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, data TEXT DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX UNIQ_C270272677153098EB3B4E33 ON notification_template (code, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_TEMPLATE_NAME ON notification_template (name, deleted);");
            $this->exec("COMMENT ON COLUMN notification_template.data IS '(DC2Type:jsonObject)'");
            $this->exec("CREATE TABLE notification_profile (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, PRIMARY KEY(id))");

            $this->exec("CREATE TABLE notification_rule (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, entity VARCHAR(255) DEFAULT NULL, occurrence VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, ignore_self_action BOOLEAN DEFAULT 'false' NOT NULL, as_owner BOOLEAN DEFAULT 'false' NOT NULL, as_follower BOOLEAN DEFAULT 'false' NOT NULL, as_assignee BOOLEAN DEFAULT 'false' NOT NULL, as_team_member BOOLEAN DEFAULT 'false' NOT NULL, as_notification_profile BOOLEAN DEFAULT 'false' NOT NULL, data TEXT DEFAULT NULL, notification_profile_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_NOTIFICATION_RULE_UNIQUE_NOTIFICATION_RULES ON notification_rule (notification_profile_id, entity, occurrence, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_RULE_NOTIFICATION_PROFILE_ID ON notification_rule (notification_profile_id, deleted);");
            $this->exec("COMMENT ON COLUMN notification_rule.data IS '(DC2Type:jsonObject)';");
            $this->exec("ALTER TABLE notification_profile ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_profile ADD modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_profile ADD created_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_profile ADD modified_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_PROFILE_CREATED_BY_ID ON notification_profile (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_PROFILE_MODIFIED_BY_ID ON notification_profile (modified_by_id, deleted);");
            $this->exec("ALTER TABLE notification_rule ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_rule ADD modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_rule ADD created_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_rule ADD modified_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_RULE_CREATED_BY_ID ON notification_rule (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_RULE_MODIFIED_BY_ID ON notification_rule (modified_by_id, deleted);");
            $this->exec("ALTER TABLE notification_template ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_template ADD modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_template ADD created_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("ALTER TABLE notification_template ADD modified_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_TEMPLATE_CREATED_BY_ID ON notification_template (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_TEMPLATE_MODIFIED_BY_ID ON notification_template (modified_by_id, deleted)");
        } else {
            $this->exec("CREATE TABLE notification_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', UNIQUE INDEX UNIQ_C270272677153098EB3B4E33 (code, deleted), INDEX IDX_NOTIFICATION_TEMPLATE_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("CREATE TABLE notification_profile (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("CREATE TABLE notification_rule (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description VARCHAR(255) DEFAULT NULL, entity VARCHAR(255) DEFAULT NULL, occurrence VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, ignore_self_action TINYINT(1) DEFAULT '0' NOT NULL, as_owner TINYINT(1) DEFAULT '0' NOT NULL, as_follower TINYINT(1) DEFAULT '0' NOT NULL, as_assignee TINYINT(1) DEFAULT '0' NOT NULL, as_team_member TINYINT(1) DEFAULT '0' NOT NULL, as_notification_profile TINYINT(1) DEFAULT '0' NOT NULL, data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', notification_profile_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_NOTIFICATION_RULE_UNIQUE_NOTIFICATION_RULES (notification_profile_id, entity, occurrence, deleted), INDEX IDX_NOTIFICATION_RULE_NOTIFICATION_PROFILE_ID (notification_profile_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("ALTER TABLE notification_profile ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL, ADD created_by_id VARCHAR(24) DEFAULT NULL, ADD modified_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_PROFILE_CREATED_BY_ID ON notification_profile (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_PROFILE_MODIFIED_BY_ID ON notification_profile (modified_by_id, deleted);");
            $this->exec("ALTER TABLE notification_rule ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL, ADD created_by_id VARCHAR(24) DEFAULT NULL, ADD modified_by_id VARCHAR(24) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_RULE_CREATED_BY_ID ON notification_rule (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_RULE_MODIFIED_BY_ID ON notification_rule (modified_by_id, deleted);");
            $this->exec("ALTER TABLE notification_template ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL, ADD created_by_id VARCHAR(24) DEFAULT NULL, ADD modified_by_id VARCHAR(24) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_TEMPLATE_CREATED_BY_ID ON notification_template (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_NOTIFICATION_TEMPLATE_MODIFIED_BY_ID ON notification_template (modified_by_id, deleted)");
        }

        $this->getConfig()->set('sendOutNotifications', !$this->getConfig()->get('disableEmailDelivery'));

        self::createNotificationDefaultNotificationProfile($this->getConnection(), $this->getConfig());

        try {
            $preferences = $this->getConnection()->createQueryBuilder()
                ->select('id', 'data')
                ->from('preferences')
                ->fetchAllAssociative();

            foreach ($preferences as $preference) {
                $data = @json_decode($preference['data'], true);
                if (empty($data)) {
                    continue;
                }
                $data['receiveNotifications'] = true;
                $data['notificationProfileId'] = "default";

                $this->getConnection()->createQueryBuilder()
                    ->update('preferences')
                    ->set('data', ':data')
                    ->where('id = :id')
                    ->setParameter('id', $preference['id'])
                    ->setParameter('data', json_encode($data))
                    ->executeStatement();
            }
        }catch (\Throwable $e){

        }
    }

    public function down(): void
    {
        $this->exec("DROP TABLE notification_rule;");
        $this->exec("DROP TABLE notification_profile;");
        $this->exec("DROP TABLE notification_template;");
        $this->getConfig()->set('disableEmailDelivery', !$this->getConfig()->get('sendOutNotifications'));

    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    public static function createNotificationDefaultNotificationProfile(Connection $connection, Config $config)
    {
        $defaultProfileId = 'defaultProfileId';
        $defaultProfileName = 'Default Notification Profile';

        $config->set('defaultNotificationProfileId', $defaultProfileId);
        $config->set('defaultNotificationProfileName', $defaultProfileName);
        $config->save();

        try {
            $connection->createQueryBuilder()
                ->insert('notification_profile')
                ->values([
                    'id' => ':id',
                    'name' => ':name',
                    'is_active' => ':is_active',
                ])
                ->setParameter('id', 'defaultProfileId')
                ->setParameter('name', $defaultProfileName)
                ->setParameter('is_active', true, ParameterType::BOOLEAN)
                ->executeStatement();
        } catch (\Throwable $e) {

        }

        $rules = [
            [
                "id" => Util::generateId(),
                "name" => "Entity Update",
                "entity" => '',
                "occurrence" => 'updating',
                "notification_profile_id" => $defaultProfileId,
                "is_active" => true,
                "ignore_self_action" => true,
                "as_owner" => true,
                "as_follower" => true,
                "as_assignee" => true,
                "as_team_member" => true,
                "as_notification_profile" => false,
                "data" => [
                    "field" => [
                        "systemActive" => true,
                        "emailActive" => true,
                        "systemTemplateId" => "systemUpdateEntity",
                        "emailTemplateId" => "emailUpdateEntity"
                    ],
                ],
                "templates" => [
                    "system" => [
                        "id" => "systemUpdateEntity",
                        "name" => "Entity Updated",
                        "data" => [
                            "field" => [
                                "body" => '<p>{{actionUser.name}} made update  on {{entityName}} {{entity.name}}.</p>
<p><a href="{{entityUrl}}">View</a></p>',
                                "bodyDeDe" => '<p>{{actionUser.name}} hat eine Aktualisierung an {{entityName}} vorgenommen {{entity.name}}.</p>

<p><a href="{{entityUrl}}">Siehe</a></p>',
                                "bodyUkUa" => '<p>{{actionUser.name}} зробив оновлення для {{entityName}} {{entity.name}}.</p>

<p><a href="{{entityUrl}}">Вигляд</a></p>'
                            ]
                        ]
                    ],
                    "email" => [
                        "id" => "emailUpdateEntity",
                        "name" => "Entity Updated",
                        "data" => [
                            "field" => [
                                "subject" => "Update: [{{ entityName }}] {{entity.name}}",
                                "subjectDeDe" => "Update: [{{ entityName }}] {{entity.name}}",
                                "subjectUkUa" => "Оновлення: [{{ entityName }}] {{entity.name}}",
                                "body" => '<p>{{actionUser.name}} made update  on {{entityName}} {{entity.name}}.</p>
<p><a href="{{entityUrl}}">View</a></p>',
                                "bodyDeDe" => '<p>{{actionUser.name}} hat eine Aktualisierung an {{entityName}} vorgenommen {{entity.name}}.</p>

<p><a href="{{entityUrl}}">Siehe</a></p>',
                                "bodyUkUa" => '<p>{{actionUser.name}} зробив оновлення для {{entityName}} {{entity.name}}.</p>

<p><a href="{{entityUrl}}">Вигляд</a></p>'
                            ]
                        ]
                    ]
                ]
            ],
            [
                "id" => Util::generateId(),
                "name" => "Note Creation Without parent",
                "entity" => 'Note',
                "occurrence" => 'creation',
                "notification_profile_id" => $defaultProfileId,
                "is_active" => true,
                "ignore_self_action" => true,
                "as_owner" => true,
                "as_follower" => true,
                "as_assignee" => true,
                "as_team_member" => true,
                "as_notification_profile" => false,
                "data" => [
                    "field" => [
                        "systemActive" => true,
                        "emailActive" => true,
                        "systemTemplateId" => "systemNotePost",
                        "emailTemplateId" => "emailNotePost"
                    ],
                ],
            ],
            [
                "id" => Util::generateId(),
                "name" => "Note Creation in Entity",
                "entity" => '',
                "occurrence" => 'note_created',
                "notification_profile_id" => $defaultProfileId,
                "is_active" => true,
                "ignore_self_action" => true,
                "as_owner" => true,
                "as_follower" => true,
                "as_assignee" => true,
                "as_team_member" => false,
                "as_notification_profile" => false,
                "data" => [
                    "field" => [
                        "systemActive" => true,
                        "emailActive" => true,
                        "systemTemplateId" => "systemNotePost",
                        "emailTemplateId" => "emailNotePost"
                    ],
                ],
                "templates" => [
                    "system" => [
                        'id' => 'systemNotePost',
                        'name' => 'Note Creation',
                        'data' => [
                            'field' => [
                                "body" => '<p>{{actionUser.name}} posted  {% if parent %}  on {{parentName}} {{parent.name}}. {% endif %}</p>
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe" => '<p>{{actionUser.name}} auf{% if parent %}  {{parentName}} {{parent.name}} gepostet. {% endif %}</p>
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyUkUa" => '<p>{{actionUser.name}} опублікував {% if parent %} на {{parentName}} {{parent.name}}. {% endif %}</p> <p>
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">Вигляд</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">Вигляд</a></p>
{% endif %}'
                            ]
                        ]
                    ],
                    "email" => [
                        'id' => 'emailNotePost',
                        "name" => "Note creation",
                        "data" => [
                            "field" => [
                                "subject" => 'Post: [{{ parentName }}] {{parent.name}}',
                                "subjectDeDe" => 'Post: [{{ parentName }}] {{parent.name}}',
                                "subjectUkUa" => 'Post: [{{ parentName }}] {{parent.name}}',
                                "body" => '<p>{{actionUser.name}} posted  {% if parent %}  on {{parentName}} {{parent.name}}. {% endif %}</p>
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe" => '<p>{{actionUser.name}} auf{% if parent %}  {{parentName}} {{parent.name}} gepostet. {% endif %}</p>
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyUkUa" => '<p>{{actionUser.name}} опублікував {% if parent %} на {{parentName}} {{parent.name}}. {% endif %}</p> <p>
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">Вигляд</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">Вигляд</a></p>
{% endif %}'
                            ]
                        ],
                    ]
                ]
            ],
            [
                "id" => Util::generateId(),
                "name" => "Mention",
                "entity" => '',
                "occurrence" => 'mentioned',
                "notification_profile_id" => $defaultProfileId,
                "is_active" => true,
                "ignore_self_action" => true,
                "as_owner" => false,
                "as_follower" => false,
                "as_assignee" => false,
                "as_team_member" => false,
                "as_notification_profile" => false,
                "data" => [
                    "field" => [
                        "systemActive" => true,
                        "emailActive" => true,
                        "systemTemplateId" => "systemMention",
                        "emailTemplateId" => "emailMention"
                    ]
                ],
                "templates" => [
                    "system" => [
                        "id" => "systemMention",
                        "name" => 'Mention',
                        "data" => [
                            "field" => [
                                "body" => '<p>You were mentioned in post by {{actionUser.name}}.</p>
{% if parent %}
<p>Related to: {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe" => '<p>Sie wurden in einem Beitrag von {{actionUser.name}} erwähnt.</p>
{% if parent %}
<p>Verwandt mit:  {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">Siehe</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">Siehe</a></p>
{% endif %}',
                                "bodyUkUa" => '<p>Вас було згадано у дописі користувача {{actionUser.name}}.</p>{% if parent %}
<p>Пов\'язано з:  {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">Вигляд</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">Вигляд</a></p>
{% endif %}'
                            ]
                        ]
                    ],
                    "email" => [
                        "id" => "emailMention",
                        "name" => "Mention",
                        "data" => [
                            "field" => [
                                "subject" => "You were mentioned",
                                "subjectDeDe" => "Sie wurden erwähnt",
                                "subjectUkUa" => "Тебе згадували",
                                "body" => '<p>You were mentioned in post by {{actionUser.name}}.</p>
{% if parent %}
<p>Related to: {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe" => '
 <p>Sie wurden in einem Beitrag von {{actionUser.name}} erwähnt.</p>
{% if parent %}
<p>Verwandt mit:  {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">Siehe</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">Siehe</a></p>
{% endif %}',
                                "bodyUkUa" => '<p>Вас було згадано у дописі користувача {{actionUser.name}}.</p>{% if parent %}
<p>Пов\'язано з:  {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">Вигляд</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">Вигляд</a></p>
{% endif %}'
                            ]
                        ]
                    ]
                ]
            ],
            [
                "id" => Util::generateId(),
                "name" => "Assignment/Ownership",
                "entity" => '',
                "occurrence" => 'ownership_assignment',
                "notification_profile_id" => $defaultProfileId,
                "is_active" => true,
                "ignore_self_action" => true,
                "as_owner" => true,
                "as_follower" => false,
                "as_assignee" => true,
                "as_team_member" => false,
                "as_notification_profile" => false,
                "data" => [
                    "field" => [
                        "systemActive" => true,
                        "emailActive" => true,
                        "systemTemplateId" => "systemOwnerAssign",
                        "emailTemplateId" => "emailOwnerAssign"
                    ],
                ],
                "templates" => [
                    "system" => [
                        "id" => 'systemOwnerAssign',
                        "type" => "system",
                        "name" => "Assignment/Ownership",
                        "data" => [
                            "field" => [
                                "body" => '{% if isAssignment %}
<p>{{actionUser.name}} has assigned {{entityName}} to  {% if notifyUser.id == assignedUser.id %} you. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">View</a></p>
{% else %}
<p>{{actionUser.name}} has set {% if notifyUser.id == ownerUser.id %} you {% else %}  {{ownerUser.name}} {% endif %} as owner for {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">View</a></p>
{% endif %}
',
                                "bodyDeDe" => '
{% if  isAssignment %}
<p>{{actionUser.name}} hat Ihnen {{entityName}}   {% if notifyUser.id == assignedUser.id %} zugewiesen. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Siehe</a></p>
{% else %}
<p>{{actionUser.name}} hat {% if notifyUser.id == ownerUser.id %} Sie {% else %}  {{ownerUser.name}} {% endif %} als Eigentümer für {{entityName}}.</p><p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Siehe</a></p>
{% endif %} 
',
                                "bodyUkUa" => '
{% if isAssignment %}
<p>{{actionUser.name}} призначив {% if notifyUser.id == assignedUser.id %} вам {% else %} {{assignedUser.name}}. {% endif %} {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Вигляд</a></p>
{% else %}
<p>{{actionUser.name}} встановив {% if notifyUser.id == ownerUser.id %} вам {% else %}  {{ownerUser.name}} {% endif  %} як власника для {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Вигляд</a></p>
{% endif %}
                              '
                            ]
                        ]
                    ],
                    "email" => [
                        "id" => 'emailOwnerAssign',
                        "type" => "system",
                        "name" => "Assignment/Ownership",
                        "data" => [
                            "field" => [
                                "subject" => '{% if isAssignment %}
Assigned to {% if notifyUser.id == assignedUser.id %} you {% else %}  {{assignedUser.name}} {% endif %}: [{{entityType}}] {{entity.name}}
{% else %}
Marked as owner: [{{entityType}}] {{entity.name}}
{% endif %}',
                                "subjectDeDe" => '{% if isAssignment  %}
Ihnen {% if notifyUser.id == assignedUser.id %} zugewiesen. {% else %}  {{assignedUser.name}} {% endif %} : [{{entityType}}] {{entity.name}}
{% else %}
Markiert als Eigentümer: [{{entityType}}] {{entity.name}}
{% endif %}',
                                "subjectUkUa" => '{% if isAssignment %}
Призначено {% if notifyUser.id == assignedUser.id %} вам {% else %} {{assignedUser.name}} {% endif %}: [{{entityType}}] {{entity.name}}
{% else %}
Позначено як власник: [{{entityType}}] {{entity.name}}
{% endif %}',
                                "body" => '{% if isAssignment %}
<p>{{actionUser.name}} has assigned {{entityName}} to  {% if notifyUser.id == assignedUser.id %} you. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">View</a></p>
{% else %}
<p>{{actionUser.name}} has set {% if notifyUser.id == ownerUser.id %} you {% else %}  {{ownerUser.name}} {% endif %} as owner for {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">View</a></p>
{% endif %}
',
                                "bodyDeDe" => '
{% if isAssignment %}
<p>{{actionUser.name}} hat Ihnen {{entityName}}   {% if notifyUser.id == assignedUser.id %} zugewiesen. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Siehe</a></p>
{% else %}
<p>{{actionUser.name}} hat {% if notifyUser.id == ownerUser.id %} Sie {% else %}  {{ownerUser.name}} {% endif %} als Eigentümer für {{entityName}}.</p><p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Siehe</a></p>
{% endif %} 
',
                                "bodyUkUa" => '
{% if isAssignment %}
<p>{{actionUser.name}} призначив {% if notifyUser.id == assignedUser.id %} вам {% else %} {{assignedUser.name}}. {% endif %} {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Вигляд</a></p>
{% else %}
<p>{{actionUser.name}} встановив {% if notifyUser.id == ownerUser.id %} вам {% else %}  {{ownerUser.name}} {% endif  %} як власника для {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Вигляд</a></p>
{% endif %}
                              '
                            ]
                        ]
                    ]
                ]
            ]
        ];


        foreach ($rules as $rule) {
            if (!empty($templates = $rule['templates'])) {
                foreach ($templates as $type => $template) {
                    try {
                        $connection->createQueryBuilder()
                            ->insert('notification_template')
                            ->values([
                                'id' => ':id',
                                'name' => ':name',
                                'data' => ':data',
                                'type' => ':type'
                            ])
                            ->setParameter('id', $template['id'])
                            ->setParameter('name', $template['name'])
                            ->setParameter('data', json_encode($template['data']))
                            ->setParameter('type', $type)
                            ->executeStatement();
                    } catch (\Throwable $e) {

                    }
                }

            }

            try {
                $query = $connection->createQueryBuilder()
                    ->insert('notification_rule');
                $values = [];
                foreach ($rule as $key => $value) {
                    if ($key === 'templates') continue;
                    $values[$key] = ":$key";
                    $query = $query
                        ->setParameter($key, is_array($value) ? json_encode($value) : $value, is_array($value) ? ParameterType::STRING : Mapper::getParameterType($value));
                }
                $query->values($values);
                $query->executeStatement();
            } catch (\Throwable $e) {
            }
        }
    }

}
