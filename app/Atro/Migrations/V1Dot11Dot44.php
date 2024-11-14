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

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot11Dot44 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-11-14 14:00:00');
    }

    public function up(): void
    {
        @mkdir(ReferenceData::DIR_PATH);
        $result = [];

        $query = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from('email_template')
            ->where('deleted = :false')
            ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->executeQuery();

        while (($row = $query->fetchAssociative()) !== false) {
            if (empty($row['code'])) {
                $row['code'] = $row['name'];
            }

            foreach ($row as $column => $value) {
                $result[$row['code']][Util::toCamelCase($column)] = $value;
            }
        }

        $result = array_merge($result, self::getDefaultEmailTemplates());
        file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'EmailTemplate.json', json_encode($result));

        $this->updateComposer('atrocore/core', '^1.11.41');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    public static function getDefaultEmailTemplates(): array
    {
        $datetime = (new \DateTime())->format('Y-m-d H:i:s');

        return [
            'emailPasswordChangeRequest' => [
                'id'               => Util::generateId(),
                'name'             => 'Password Change Request',
                'nameDeDe'         => 'Anforderung zur Passwortänderung',
                'nameUkUa'         => 'Запит на зміну пароля',
                'nameFrFr'         => 'Demande de changement de mot de passe',
                'code'             => 'emailPasswordChangeRequest',
                'subject'          => 'Password Change Request',
                'subjectDeDe'      => 'Anforderung zur Passwortänderung',
                'subjectUkUa'      => 'Запит на зміну пароля',
                'subjectFrFr'      => 'Demande de changement de mot de passe',
                'body'             => '<h2>You have sent a password change request</h2><p>If this was you, please follow <a href="{{ link }}" target="_blank">the link</a> and change your password. Otherwise, you can ignore this email.</p><p>Provided unique URL has a limited duration and will be expired soon.</p>',
                'bodyDeDe'         => '<h2>Sie haben eine Anfrage zur Passwortänderung gesendet</h2><p>Wenn Sie das waren, folgen Sie bitte <a href="{{ link }}" target="_blank">dem Link</a> und ändern Sie Ihr Passwort. Andernfalls können Sie diese E-Mail ignorieren.</p><p>Die bereitgestellte eindeutige URL hat eine begrenzte Gültigkeitsdauer und wird bald ablaufen.</p>',
                'bodyFrFr'         => "<h2>Vous avez envoyé une demande de changement de mot de passe</h2><p>S'il s'agit de vous, veuillez suivre <a href=\"{{ link }}\" target=\"_blank\">le lien</a> et changer votre mot de passe. Sinon, vous pouvez ignorer cet e-mail.</p><p>L'URL unique fournie a une durée limitée et expirera bientôt.</p>",
                'bodyUkUa'         => '<h2>Ви надіслали запит на зміну пароля</h2><p>Якщо це були Ви, перейдіть за <a href="{{ link }}" target="_blank">посиланням</a> та змініть свій пароль. В іншому випадку ви можете проігнорувати цей лист.</p><p>Надана унікальна URL-адреса має обмежений термін дії, який незабаром спливе.</p>',
                'allowAttachments' => false,
                'createdAt'        => $datetime,
                'createdById'      => 'system'
            ],
            'emailPasswordReset'         => [
                'id'               => Util::generateId(),
                'name'             => 'Password Reset',
                'nameDeDe'         => 'Passwort zurücksetzen',
                'nameUkUa'         => 'Пароль скинуто',
                'nameFrFr'         => 'Réinitialisation du mot de passe',
                'code'             => 'emailPasswordReset',
                'subject'          => 'Password Reset',
                'subjectDeDe'      => 'Passwort zurücksetzen',
                'subjectUkUa'      => 'Ваш пароль було скинуто',
                'subjectFrFr'      => 'Réinitialisation du mot de passe',
                'body'             => '<h2>Your password is not valid anymore</h2><p>To set a new password, please follow <a href="{{ link }}" target="_blank">the link</a></p><p>Provided unique URL has a limited duration and will be expired soon.</p>',
                'bodyDeDe'         => '<h2>Ihr Passwort ist nicht mehr gültig</h2><p>Um ein neues Passwort zu setzen, folgen Sie bitte <a href="{{ link }}" target="_blank">dem Link</a></p><p>Die bereitgestellte eindeutige URL hat eine begrenzte Gültigkeitsdauer und wird bald ablaufen.</p>',
                'bodyFrFr'         => "<h2>Votre mot de passe n'est plus valide</h2><p>Pour définir un nouveau mot de passe, veuillez suivre <a href=\"{{ link }}\" target=\"_blank\">le lien</a></p><p>L'URL unique fournie a une durée limitée et sera bientôt expirée.</p>",
                'bodyUkUa'         => '<h2>Ваш пароль більше не дійсний</h2><p>Щоб встановити новий пароль, перейдіть за <a href="{{ link }}" target="_blank">посиланням</a></p><p>Надана унікальна URL-адреса має обмежений термін дії, який незабаром спливе.</p>',
                'allowAttachments' => false,
                'createdAt'        => $datetime,
                'createdById'      => 'system'
            ]
        ];
    }
}
