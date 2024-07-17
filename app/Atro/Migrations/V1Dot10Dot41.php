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
use Doctrine\DBAL\Connection;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;

class V1Dot10Dot41 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-17 10:00:00');
    }

    public function up(): void
    {
        $this->exec('DROP TABLE if exists email_template;');

        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE email_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', code VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, email_to TEXT DEFAULT NULL, email_cc TEXT DEFAULT NULL, subject TEXT DEFAULT NULL, body TEXT DEFAULT NULL, allow_attachments BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, connection_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX UNIQ_9C0600CA77153098EB3B4E33 ON email_template (code, deleted);");
            $this->exec("CREATE INDEX IDX_EMAIL_TEMPLATE_CONNECTION_ID ON email_template (connection_id, deleted);");
            $this->exec("CREATE INDEX IDX_EMAIL_TEMPLATE_CREATED_BY_ID ON email_template (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_EMAIL_TEMPLATE_MODIFIED_BY_ID ON email_template (modified_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_EMAIL_TEMPLATE_NAME ON email_template (name, deleted);");
            $this->exec("COMMENT ON COLUMN email_template.email_to IS '(DC2Type:jsonArray)';");
            $this->exec("COMMENT ON COLUMN email_template.email_cc IS '(DC2Type:jsonArray)';");
        } else {
            $this->exec("CREATE TABLE email_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', code VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, email_to LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', email_cc LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', subject LONGTEXT DEFAULT NULL, body LONGTEXT DEFAULT NULL, allow_attachments TINYINT(1) DEFAULT '0' NOT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, connection_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX UNIQ_9C0600CA77153098EB3B4E33 (code, deleted), INDEX IDX_EMAIL_TEMPLATE_CONNECTION_ID (connection_id, deleted), INDEX IDX_EMAIL_TEMPLATE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_EMAIL_TEMPLATE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_EMAIL_TEMPLATE_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        }

        foreach ($this->getConfig()->get('locales', []) as $locale) {
            if ($locale['language'] === 'en_US') {
                continue;
            }
            $locale = strtolower($locale['language']);
            if ($this->isPgSQL()) {
                $this->exec("ALTER TABLE email_template ADD subject_$locale TEXT DEFAULT NULL");
                $this->exec("ALTER TABLE email_template ADD body_$locale TEXT DEFAULT NULL");
            } else {
                $this->exec("ALTER TABLE email_template ADD subject_$locale LONGTEXT DEFAULT NULL");
                $this->exec("ALTER TABLE email_template ADD body_$locale LONGTEXT DEFAULT NULL");
            }

        }

        self::createNotificationEmailTemplates($this->getConnection(), $this->getConfig());
    }

    public function down(): void
    {
        $this->exec('DROP TABLE email_template;');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    public static function createNotificationEmailTemplates(Connection $connection, Config $config)
    {
        $data = [
            [
                'id'            => 'assignment',
                'name'          => 'Assignment',
                'code'          => 'assignment',
                'subject'       => "Assigned to you: [{{entityType}}] {{entity.name}}",
                'subject_de_de' => 'Ihnen zugewiesen: [{{entityType}}] {{entity.name}}',
                'body'          => '<p>{{assignerUserName}} has assigned {{entityTypeLowerFirst}} to you.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{recordUrl}}">View</a></p>',
                'body_de_de'    => '<p>{{assignerUserName}} hat Ihnen {{entityType}} zugewiesen.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{recordUrl}}">Ansehen</a></p>'
            ],
            [
                'id'            => 'mention',
                'name'          => 'Mention',
                'code'          => 'mention',
                'subject'       => "You were mentioned",
                'subject_de_de' => 'Sie wurden erwähnt',
                'body'          => '<p>You were mentioned in post by {{userName}}.</p>
{{#if parentName}}
<p>Related to: {{parentName}}</p>
{{/if}}
<p>{{post}}</p>
<p><a href="{{url}}">View</a></p>
',
                'body_de_de'    => '<p>Sie wurden in Post von {{userName}} erwähnt.</p>
{{#if parentName}}
<p>Im Zusammenhang mit: {{parentName}}</p>
{{/if}}
<p>{{post}}</p>
<p><a href="{{url}}">Ansehen</a></p>
'
            ],
            [
                'id'            => 'notePost',
                'name'          => 'Note Post',
                'code'          => 'notePost',
                'subject'       => 'Post: [{{{entityType}}}] {{{name}}}',
                'subject_de_de' => 'Post: [{{{entityType}}}] {{{name}}}',
                'body'          => '<p>{{userName}} posted on {{entityTypeLowerFirst}} {{parentName}}.</p>
<p>{{post}}</p>
<p><a href="{{url}}">View</a></p>',
                'body_de_de'    => '<p>{{userName}} auf {{entityTypeLowerFirst}} {{parentName}} gepostet.</p>
<p>{{post}}</p>
<p><a href="{{url}}">Ansehen</a></p>'
            ],
            [
                'id'            => 'notePostNoParent',
                'name'          => 'Note Post No Parent',
                'code'          => 'notePostNoParent',
                'subject'       => 'Post',
                'subject_de_de' => 'Post',
                'body'          => '<p>{{userName}} posted.</p>
<p>{{post}}</p>
<p><a href="{{url}}">View</a></p>',
                'body_de_de'    => '<p>{{userName}} gepostet.</p>
<p>{{post}}</p>
<p><a href="{{url}}">Ansehen</a></p>'
            ],
            [
                'id'            => 'ownership',
                'name'          => 'Ownership',
                'code'          => 'ownership',
                'subject'       => 'Marked as owner: [{{entityType}}] {{entity.name}}',
                'subject_de_de' => 'Markiert als Eigentümer: [{{entityType}}] {{entity.name}}',
                'body'          => '<p>{{assignerUserName}} has set you as owner for {{entityTypeLowerFirst}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{recordUrl}}">View</a></p>',
                'body_de_de'    => '<p>{{assignerUserName}} markierte Sie als Eigentümer von {{entityType}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{recordUrl}}">Eintrag öffnen</a></p>'
            ]
        ];


        foreach ($data as $template) {
            try {
                $query = $connection->createQueryBuilder()
                    ->insert('email_template')
                    ->values([
                        'id'      => ':id',
                        'name'    => ':name',
                        'code'    => ':code',
                        'subject' => ':subject',
                        'body'    => ':body'
                    ]);

                if (in_array('de_DE', array_column($config->get('locales'), 'language'))) {
                    $query->setValue('subject_de_de', ':subject_de_de')
                        ->setValue('body_de_de', ':body_de_de');
                }

                $query->setParameters($template)
                    ->executeStatement();
            } catch (\Exception $e) {

            }

        }
    }
}
