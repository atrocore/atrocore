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
        return new \DateTime('2024-07-15 15:00:00');
    }

    public function up(): void
    {
        $this->exec('DROP TABLE if exists email_template;');
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;


        $table = $toSchema->createTable('email_template');
        $table->addColumn('id', 'string', ['length' => 24]);
        $table->addColumn('name', 'string', ['notnull' => false]);
        $table->addColumn('code', 'string', ['notnull' => false]);
        $table->addColumn('email_cc', 'jsonArray', ['notnull' => false]);
        $table->addColumn('email_to', 'jsonArray', ['notnull' => false]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('subject', 'text', ['notnull' => false]);
        $table->addColumn('body', 'text', ['notnull' => false]);
        foreach ($this->getConfig()->get('locales', []) as $locale) {
            if ($locale['language'] === $this->getConfig()->get('language')) {
                continue;
            }
            $locale = strtolower($locale['language']);

            $table->addColumn("subject_$locale", 'text', ['notnull' => false]);
            $table->addColumn("body_$locale", 'text', ['notnull' => false]);
        }
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
        $table->addColumn('modified_by_id', 'string', ['notnull' => false, 'length' => 24]);
        $table->addColumn('created_by_id', 'string', ['notnull' => false, 'length' => 24]);
        $table->addColumn('allow_attachments', 'boolean', ['default' => false]);
        $table->addColumn('connection_id', 'string', ['length' => 24, 'notnull' => false]);
        $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['code', 'deleted'], 'UNIQ_9C0600CA77153098EB3B4E33');
        $table->addIndex(['connection_id', 'deleted'], 'IDX_EMAIL_TEMPLATE_CONNECTION_ID');
        $table->addIndex(['created_by_id', 'deleted'], 'IDX_EMAIL_TEMPLATE_CREATED_BY_ID');
        $table->addIndex(['modified_by_id', 'deleted'], 'IDX_EMAIL_TEMPLATE_MODIFIED_BY_ID');
        $table->addIndex(['name', 'deleted'], 'IDX_EMAIL_TEMPLATE_NAME');


        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
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
