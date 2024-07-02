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
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot36 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-02 09:25:00');
    }

    public function up(): void
    {
        $smtpConnection = $this->getConnection()->createQueryBuilder()
            ->from($this->getConnection()->quoteIdentifier('connection'), 'c')
            ->select('id')
            ->where('c.type = :type')
            ->andWhere('c.deleted = :deleted')
            ->setParameter('type', 'smtp')
            ->setParameter('deleted', false, ParameterType::BOOLEAN)
            ->fetchOne();

        if (empty($smtpConnection)) {
            $config = $this->getConfig();
            $id = Util::generateId();
            $name = 'Default SMTP';
            $this->getConnection()->createQueryBuilder()
                ->insert($this->getConnection()->quoteIdentifier('connection'))
                ->values([
                    'id'   => ':id',
                    'type' => ':type',
                    'name' => ':name',
                    'data' => ':data'
                ])
                ->setParameter('id', $id)
                ->setparameter('name', $name)
                ->setparameter('type', 'smtp')
                ->setparameter('data', json_encode([
                    'smtpServer'               => $config->get('smtpServer'),
                    'smtpPassword'             => !empty($config->get('smtpPassword')) ? $this->encryptPassword($config->get('smtpPassword')) : '',
                    'smtpPort'                 => $config->get('smtpPort'),
                    'smtpSecurity'             => $config->get('smtpSecurity'),
                    'smtpUsername'             => $config->get('smtpUsername'),
                    'outboundEmailFromName'    => $config->get('outboundEmailFromName'),
                    'outboundEmailFromAddress' => $config->get('outboundEmailFromAddress'),
                ]))
                ->executeStatement();

            $config->remove('smtpServer');
            $config->remove('smtpPassword');
            $config->remove('smtpPort');
            $config->remove('smtpSecurity');
            $config->remove('smtpUsername');
            $config->remove('outboundEmailFromAddress');
            $config->remove('outboundEmailFromName');
            $config->set('notificationSmtpConnectionId', $id);
            $config->set('notificationSmtpConnectionName', $name);

            $config->save();
        }
    }

    public function down(): void
    {
        $config = $this->getConfig();

        $smtpConnection = $this->getConnection()->createQueryBuilder()
            ->from($this->getConnection()->quoteIdentifier('connection'), 'c')
            ->select('id', 'data')
            ->where('id = :id')
            ->andWhere('c.deleted = :deleted')
            ->setParameter('id', $config->get('notificationSmtpConnectionId'))
            ->setParameter('deleted', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (empty($smtpConnection)) {
            return;
        }

        $data = @json_decode($smtpConnection['data'], true);
        if (!empty($data)) {
            $config->set('smtpServer', $data['smtpServer']);
            $config->set('smtpPassword', !empty($data['smtpPassword']) ? $this->decryptPassword($data['smtpPassword']) : '');
            $config->set('smtpPort', $data['smtpPort']);
            $config->set('smtpSecurity', $data['smtpSecurity']);
            $config->set('smtpUsername', $data['smtpUsername']);
            $config->set('outboundEmailFromAddress', $data['outboundEmailFromAddress']);
            $config->set('outboundEmailFromName', $data['outboundEmailFromName']);
            $config->remove('notificationSmtpConnectionId');
            $config->remove('notificationSmtpConnectionName');

            $config->save();
        }
    }

    public function encryptPassword(string $password): string
    {
        return openssl_encrypt($password, $this->getCypherMethod(), $this->getSecretKey(), 0, $this->getByteSecretIv());
    }

    public function decryptPassword(string $hash): string
    {
        return openssl_decrypt($hash, $this->getCypherMethod(), $this->getSecretKey(), 0, $this->getByteSecretIv());
    }

    protected function getCypherMethod(): string
    {
        return $this->getConfig()->get('cypherMethod', 'AES-256-CBC');
    }

    protected function getSecretKey(): string
    {
        return $this->getConfig()->get('passwordSalt', 'ATRO');
    }

    protected function getByteSecretIv(): string
    {
        $ivFile = 'data/byte-secret-iv-' . strtolower($this->getCypherMethod()) . '.txt';
        if (file_exists($ivFile)) {
            $iv = file_get_contents($ivFile);
        } else {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->getCypherMethod()));
            file_put_contents($ivFile, $iv);
        }

        return $iv;
    }

    protected  function previewTemplateMigration()
    {
        if($this->isPgSQL()){
            $this->exec("ALTER TABLE html_template RENAME TO preview_template;");
            $this->exec("CREATE TABLE preview_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', template TEXT DEFAULT NULL, entity_type VARCHAR(255) DEFAULT 'Product', data TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT 'true' NOT NULL, PRIMARY KEY(id));
COMMENT ON COLUMN preview_template.data IS '(DC2Type:jsonObject)';");
        }else{
            $this->exec("RENAME table html_template TO preview_template;");
            $this->exec("CREATE TABLE preview_template (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, delete
d TINYINT(1) DEFAULT '0', template LONGTEXT DEFAULT NULL, entity_type VARCHAR(255) DEFAULT 'Pr
oduct', data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', is_active TINYINT(1) DEFAULT '1' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        }
    }

     protected function exec(string $query): void
     {
         try {
             $this->getPDO()->exec($query);
         } catch (\Throwable $e) {
         }
     }
}
