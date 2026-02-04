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

namespace Atro\Seeders;

use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\IdGenerator;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class NotificationProfileSeeder extends AbstractSeeder
{
    public function run(): void
    {
        $defaultProfileId = IdGenerator::toUuid('defaultProfileId');
        $defaultProfileName = 'Default Notification Profile';
        $emailTemplates = [];

        $this->getConfig()->set('defaultNotificationProfileId', $defaultProfileId);
        $this->getConfig()->set('defaultNotificationProfileName', $defaultProfileName);
        $this->getConfig()->save();

        try {
            $this->getConnection()->createQueryBuilder()
                ->insert('notification_profile')
                ->values([
                    'id'        => ':id',
                    'name'      => ':name',
                    'is_active' => ':is_active',
                ])
                ->setParameter('id', $defaultProfileId)
                ->setParameter('name', $defaultProfileName)
                ->setParameter('is_active', true, ParameterType::BOOLEAN)
                ->executeStatement();
        } catch (\Throwable $e) {
        }

        $rules = $this->getDefaultRules();
        foreach ($rules as $rule) {
            if (!empty($rule['templates'])) {
                $templates = $rule['templates'];
                foreach ($templates as $type => $template) {
                    if ($type === 'email') {
                        $emailTemplates[$template['id']] = [
                            'id'        => $template['id'],
                            'code'      => $template['id'],
                            'name'      => $template['name'],
                            'subject'   => $template['data']['field']['subject'] ?? '',
                            'body'      => $template['data']['field']['body'] ?? '',
                            'createdAt' => date('Y-m-d H:i:s')
                        ];

                        continue;
                    }

                    try {
                        $this->getConnection()->createQueryBuilder()
                            ->insert('notification_template')
                            ->values([
                                'id'   => ':id',
                                'name' => ':name',
                                'data' => ':data'
                            ])
                            ->setParameter('id', IdGenerator::toUuid($template['id']))
                            ->setParameter('name', $template['name'])
                            ->setParameter('data', json_encode($template['data']))
                            ->executeStatement();
                    } catch (\Throwable $e) {
                    }
                }
            }

            try {
                $query = $this->getConnection()->createQueryBuilder()
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

        $emailTemplates = array_merge($emailTemplates, $this->getPasswordEmailTemplates());

        @mkdir(ReferenceData::DIR_PATH);
        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'EmailTemplate.json', json_encode($emailTemplates));
    }

    private function getDefaultRules(): array
    {
        $defaultProfileId = IdGenerator::toUuid('defaultProfileId');

        return [
            [
                "id"                      => IdGenerator::uuid(),
                "name"                    => "Entity Update",
                "entity"                  => '',
                "occurrence"              => 'updating',
                "notification_profile_id" => $defaultProfileId,
                "is_active"               => true,
                "ignore_self_action"      => true,
                "as_owner"                => true,
                "as_follower"             => true,
                "as_assignee"             => true,
                "as_team_member"          => false,
                "as_notification_profile" => false,
                "data"                    => [
                    "field" => [
                        "systemActive"     => true,
                        "emailActive"      => true,
                        "systemTemplateId" => "systemUpdateEntity",
                        "emailTemplateId"  => "emailUpdateEntity"
                    ],
                ],
                "templates"               => [
                    "system" => [
                        "id"   => "systemUpdateEntity",
                        "name" => "Entity Updated",
                        "data" => [
                            "field" => [
                                "body"     => "{% macro translateField(field, context) %}
    {{ context.updateData['fieldDefs'][field]['label'] ?? translate(field, context.language, 'fields', context.entityType) }}
{% endmacro %}

{% macro getValue(field, type, context)  %}
    {%  set updateData = context.updateData %}
    {%  set language = context.language %}
    {%  set entityType = context.entityType %}
    {%  set fieldDefs = context.updateData['fieldDefs'][field] %}

    {% if updateData['fieldTypes'][field] in ['extensibleEnum', 'link', 'measure', 'file'] %}
        {% set value = updateData['attributes'][type][field ~ 'Name'] %}
    {% elseif updateData['fieldTypes'][field]  == 'bool' %}
        {% set value = updateData['attributes'][type][field] %}
        {%  if value is not null %}
            {% set value = value ?  translate('Yes',language): translate('no',language)  %}
        {% endif %}
    {% else %}
        {% set value = updateData['attributes'][type][field] %}
    {% endif %}

    {% if value is null %}
        {% set value = 'Null' %}
    {% endif %}

    {% if  updateData['fieldTypes'][field]  == 'extensibleEnum' %}
        {%  set color = updateData['attributes'][type][field ~ 'OptionData']['color'] %}
        {% if color %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ color|generateFontColor }}; background-color:{{ color }};font-size:100%;font-weight:normal; border: solid 1px {{ color|generateBorderColor}}\">{{ value }}</span>
        {% else %}
            <code> {{ value }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field]  == 'extensibleMultiEnum' %}
        {% for optionData in updateData['attributes'][type][field ~ 'OptionsData'] %}
            {% if optionData['color'] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionData['color']|generateFontColor }}; background-color:{{ optionData['color'] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionData['color']|generateBorderColor}}\">{{  optionData['name'] }}</span>
            {% else %}
                <code style=\"background-color:{{ optionData['color'] }}\"> {{ optionData['name'] }}</code> &nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData['fieldTypes'][field] == 'color' %}
        <code style=\"color:{{ value|generateFontColor }}; background-color:{{ value }}\"> {{ value }}</code>
    {% elseif updateData['fieldTypes'][field] == 'enum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
           {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
            {% endif %}
        {% endfor %}
        {% if optionColors[value] %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[value]|generateFontColor }}; background-color:{{ optionColors[value] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[value]|generateBorderColor}}\">{{ translateOption(value, language, field, entityType) }}</span>
        {% else %}
            <code> {{ translateOption(value, language, field, entityType) }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field] == 'multiEnum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
            {% endif %}
        {% endfor %}
        {% for v in value %}
            {% if optionColors[v] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[v]|generateFontColor }}; background-color:{{ optionColors[v] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[v]|generateBorderColor}}\">{{ translateOption(v, language, field, entityType) }}</span>
            {% else %}
                <code style=\"\"> {{ translateOption(v, language, field, entityType) }}</code>&nbsp;&nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData['fieldTypes'][field] == 'file' and value != 'Null' %}
        <a href=\"{{ siteUrl }}/#File/view/{{ updateData['attributes'][type][field ~ 'Id'] }}\">{{ value }}</a>   
    {% elseif updateData['fieldTypes'][field] == 'link' and value != 'Null' %}
        <a href=\"{{ siteUrl }}/#{{ updateData['linkDefs'][field]['entity'] }}/view/{{ updateData['attributes'][type][field ~ 'Id'] }}\">{{ value }}</a>
    {% elseif updateData['fieldTypes'][field] == 'array' %}
        {{ value|join(', ') }}
    {% else %}
        <code>{{ value }}</code>
    {% endif %}
{% endmacro %}

{% macro getMessage(context) %}
    {% set updateData = context.updateData %}
    {% set language = context.language %}
    {% set entity = context.entity %}
    {% set entityType = context.entityType %}
    {% set entityName = context.entityName %}
    {% set entityUrl = context.entityUrl %}
    {% set actionUser = context.actionUser %}
    {% set notifyUser = context.notifyUser %}
    {% set  hasAssignment = 'assignedUser' in updateData['fields']  %}
    {% set isOnly = updateData['fields']|length == 1 or ('modifiedBy' in updateData['fields'] and  updateData['fields']|length == 2) %}
    {% set assignedUserId =  entity.assignedUserId %}

    {% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId  %}
        {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
        {{ actionUser.name ?? actionUser.userName }} has assigned to you  {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a>.
    {% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
        {{ actionUser.name ?? actionUser.userName }} has assigned to you {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a> and updated
    {% else %}
        {{ actionUser.name ?? actionUser.userName }}  in {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a>  updated
    {% endif %}
{% endmacro %}

{%  set shouldShowInLine = updateData['fields']|length == 1 and not updateData['diff'] %}

<div class=\"stream-head-container\">
    <div>
        {% if  shouldShowInLine %}
            {% for field in  updateData['fields'] %}
                <span class=\"text-muted message\">{{ _self.getMessage(_context) }} <code>{{_self.translateField(field, _context)}}</code> {{translate('from', language, 'streamMessages', 'Global')}}&nbsp;<span class=\"was\">{{_self.getValue(field, 'was',_context)}}</span>&nbsp;{{translate('to', language, 'streamMessages')}} <span class=\"became\">{{_self.getValue(field, 'became', _context)}}</span></span>
            {% endfor %}
        {% else %}
            <span class=\"text-muted message\"> {{ _self.getMessage(_context) }} {{ updateData['fields']|map(f => translate(f, language, 'fields', entityType))|map(f => '<code> ' ~ f ~' </code>')|join(', ')|raw }}</span>
            <a href=\"javascript:\" data-action=\"expandDetails\"><span class=\"fas fa-angle-down\"></span></a>
        {% endif %}
    </div>
</div>

<div class=\"hidden details stream-details-container\">

    {% if not shouldShowInLine %}
        {%  set hasNonDiffField = updateData['fields']|keys|length != updateData['diff']|keys|length %}
        {% if hasNonDiffField %}
            <div class=\"panel\">
                {% for field in  updateData['fields'] %}
                    {% if not updateData['diff'][field] %}
                        <div class=\"row\">
                            <div class=\"cell col-md-12 col-lg-6 form-group\">
                                <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('was', language, 'streamMessages')}}</label>
                                <div class=\"field\">{{_self.getValue(field, 'was', _context) }}</div>
                            </div>
                            <div class=\"cell col-md-12 col-lg-6 form-group\">
                                <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('become', language, 'streamMessages')}}</label>
                                <div class=\"field\">{{_self.getValue(field, 'became', _context) }}</div>
                            </div>
                        </div>
                    {% endif %}
                {% endfor %}
            </div>
        {% endif %}
    {% endif%}
    {%if updateData['diff'] %}
        <div class=\"panel diff\">
            {% for field, diff in updateData['diff'] %}
                <div class=\"row\">
                    <div class=\"cell col-md-12 col-lg-12 form-group\">
                        <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('changed', language, 'streamMessages')}}</label>
                        <div class=\"field\">{{diff|raw}}</div>
                    </div>
                </div>
            {% endfor %}
        <div>
    {% endif %}
</div>",
                                "bodyDeDe" => "{% macro translateField(field, context) %}
    {{ context.updateData['fieldDefs'][field]['label'] ?? translate(field, context.language, 'fields', context.entityType) }}
{% endmacro %}

{% macro getValue(field, type, context)  %}
    {%  set updateData = context.updateData %}
    {%  set language = context.language %}
    {%  set entityType = context.entityType %}
    {%  set fieldDefs = context.updateData['fieldDefs'][field] %}

    {% if updateData['fieldTypes'][field] in ['extensibleEnum', 'link', 'measure', 'file'] %}
        {% set value = updateData['attributes'][type][field ~ 'Name'] %}
    {% elseif updateData['fieldTypes'][field]  == 'bool' %}
        {% set value = updateData['attributes'][type][field] %}
        {%  if value is not null %}
            {% set value = value ?  translate('Yes',language): translate('no',language)  %}
        {% endif %}
    {% else %}
        {% set value = updateData['attributes'][type][field] %}
    {% endif %}

    {% if value is null %}
        {% set value = 'Null' %}
    {% endif %}

    {% if  updateData['fieldTypes'][field]  == 'extensibleEnum' %}
        {%  set color = updateData['attributes'][type][field ~ 'OptionData']['color'] %}
        {% if color %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ color|generateFontColor }}; background-color:{{ color }};font-size:100%;font-weight:normal; border: solid 1px {{ color|generateBorderColor}}\">{{ value }}</span>
        {% else %}
            <code> {{ value }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field]  == 'extensibleMultiEnum' %}
        {% for optionData in updateData['attributes'][type][field ~ 'OptionsData'] %}
            {% if optionData['color'] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionData['color']|generateFontColor }}; background-color:{{ optionData['color'] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionData['color']|generateBorderColor}}\">{{  optionData['name'] }}</span>
            {% else %}
                <code style=\"background-color:{{ optionData['color'] }}\"> {{ optionData['name'] }}</code> &nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData['fieldTypes'][field] == 'color' %}
        <code style=\"color:{{ value|generateFontColor }}; background-color:{{ value }}\"> {{ value }}</code>
    {% elseif updateData['fieldTypes'][field] == 'enum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
           {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
            {% endif %}
        {% endfor %}
        {% if optionColors[value] %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[value]|generateFontColor }}; background-color:{{ optionColors[value] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[value]|generateBorderColor}}\">{{ translateOption(value, language, field, entityType) }}</span>
        {% else %}
            <code> {{ translateOption(value, language, field, entityType) }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field] == 'multiEnum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
           {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
            {% endif %}
        {% endfor %}
        {% for v in value %}
            {% if optionColors[v] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[v]|generateFontColor }}; background-color:{{ optionColors[v] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[v]|generateBorderColor}}\">{{ translateOption(v, language, field, entityType) }}</span>
            {% else %}
                <code style=\"\"> {{ translateOption(v, language, field, entityType) }}</code>&nbsp;&nbsp;
            {% endif %}
        {% endfor %}
     {% elseif updateData['fieldTypes'][field] == 'file' and value != 'Null' %}
        <a href=\"{{ siteUrl }}/#File/view/{{ updateData['attributes'][type][field ~ 'Id'] }}\">{{ value }}</a>
    {% elseif updateData['fieldTypes'][field] == 'link' and value != 'Null' %}
        <a href=\"{{ siteUrl }}/#{{ updateData['linkDefs'][field]['entity'] }}/view/{{ updateData['attributes'][type][field ~ 'Id'] }}\">{{ value }}</a>
     {% elseif updateData['fieldTypes'][field] == 'array' %}
        {{ value|join(', ') }}
    {% else %}
        <code>{{ value }}</code>
    {% endif %}
{% endmacro %}

{% macro getMessage(context) %}
    {% set updateData = context.updateData %}
    {% set language = context.language %}
    {% set entity = context.entity %}
    {% set entityType = context.entityType %}
    {% set entityName = context.entityName %}
    {% set entityUrl = context.entityUrl %}
    {% set actionUser = context.actionUser %}
    {% set notifyUser = context.notifyUser %}
    {% set  hasAssignment = 'assignedUser' in updateData['fields']  %}
    {% set isOnly = updateData['fields']|length == 1 or ('modifiedBy' in updateData['fields'] and  updateData['fields']|length == 2) %}
    {% set assignedUserId =  entity.assignedUserId %}

    {% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId  %}
        {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
        <p>{{ actionUser.name ?? actionUser.userName }} hat Ihnen {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a>.</p>  zugewiesen
    {% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
        <p>{{ actionUser.name ?? actionUser.userName }} hat Ihnen zugewiesen und eine Aktualisierung an {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a> vorgenommen.</p>
    {% else %}
        <p>{{ actionUser.name ?? actionUser.userName }} hat Update auf {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a>. vorgenommen. </p>
    {% endif %}
{% endmacro %}

{%  set shouldShowInLine = updateData['fields']|length == 1 and not updateData['diff'] %}

<div class=\"stream-head-container\">
    <div >
        {% if  shouldShowInLine %}
            {% for field in  updateData['fields'] %}
                <span class=\"text-muted message\">{{ _self.getMessage(_context) }} <code>{{_self.translateField(field, _context)}}</code> {{translate('from', language, 'streamMessages', 'Global')}}&nbsp;<span class=\"was\">{{_self.getValue(field, 'was',_context)}}</span>&nbsp;{{translate('to', language, 'streamMessages')}} <span class=\"became\">{{_self.getValue(field, 'became', _context)}}</span></span>
            {% endfor %}
        {% else %}
            <span class=\"text-muted message\"> {{ _self.getMessage(_context) }} {{ updateData['fields']|map(f => translate(f, language, 'fields', entityType))|map(f => '<code> ' ~ f ~' </code>')|join(', ')|raw }}</span>
            <a href=\"javascript:\" data-action=\"expandDetails\"><span class=\"fas fa-angle-down\"></span></a>
        {% endif %}
    </div>
</div>

<div class=\"hidden details stream-details-container\">

    {% if not shouldShowInLine %}
        {%  set hasNonDiffField = updateData['fields']|keys|length != updateData['diff']|keys|length %}
        {% if hasNonDiffField %}
            <div class=\"panel\">
                {% for field in  updateData['fields'] %}
                    {% if not updateData['diff'][field] %}
                        <div class=\"row\">
                            <div class=\"cell col-md-12 col-lg-6 form-group\">
                                <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('was', language, 'streamMessages')}}</label>
                                <div class=\"field\">{{_self.getValue(field, 'was', _context) }}</div>
                            </div>
                            <div class=\"cell col-md-12 col-lg-6 form-group\">
                                <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('become', language, 'streamMessages')}}</label>
                                <div class=\"field\">{{_self.getValue(field, 'became', _context) }}</div>
                            </div>
                        </div>
                    {% endif %}
                {% endfor %}
            </div>
        {% endif %}
    {% endif%}
    {%if updateData['diff'] %}
        <div class=\"panel diff\">
            {% for field, diff in updateData['diff'] %}
                <div class=\"row\">
                    <div class=\"cell col-md-12 col-lg-12 form-group\">
                        <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('changed', language, 'streamMessages')}}</label>
                        <div class=\"field\">{{diff|raw}}</div>
                    </div>
                </div>
            {% endfor %}
        <div>
    {% endif %}
</div>",
                                "bodyUkUa" => "{% macro translateField(field, context) %}
    {{ context.updateData['fieldDefs'][field]['label'] ?? translate(field, context.language, 'fields', context.entityType) }}
{% endmacro %}

{% macro getValue(field, type, context)  %}
    {%  set updateData = context.updateData %}
    {%  set language = context.language %}
    {%  set entityType = context.entityType %}
    {%  set fieldDefs = context.updateData['fieldDefs'][field] %}

    {% if updateData['fieldTypes'][field] in ['extensibleEnum', 'link', 'measure', 'file'] %}
        {% set value = updateData['attributes'][type][field ~ 'Name'] %}
    {% elseif updateData['fieldTypes'][field]  == 'bool' %}
        {% set value = updateData['attributes'][type][field] %}
        {%  if value is not null %}
            {% set value = value ?  translate('Yes',language): translate('no',language)  %}
        {% endif %}
    {% else %}
        {% set value = updateData['attributes'][type][field] %}
    {% endif %}

    {% if value is null %}
        {% set value = 'Null' %}
    {% endif %}

    {% if  updateData['fieldTypes'][field]  == 'extensibleEnum' %}
        {%  set color = updateData['attributes'][type][field ~ 'OptionData']['color'] %}
        {% if color %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ color|generateFontColor }}; background-color:{{ color }};font-size:100%;font-weight:normal; border: solid 1px {{ color|generateBorderColor}}\">{{ value }}</span>
        {% else %}
            <code> {{ value }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field]  == 'extensibleMultiEnum' %}
        {% for optionData in updateData['attributes'][type][field ~ 'OptionsData'] %}
            {% if optionData['color'] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionData['color']|generateFontColor }}; background-color:{{ optionData['color'] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionData['color']|generateBorderColor}}\">{{  optionData['name'] }}</span>
            {% else %}
                <code style=\"background-color:{{ optionData['color'] }}\"> {{ optionData['name'] }}</code> &nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData['fieldTypes'][field] == 'color' %}
        <code style=\"color:{{ value|generateFontColor }}; background-color:{{ value }}\"> {{ value }}</code>
    {% elseif updateData['fieldTypes'][field] == 'enum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
            {% endif %}
        {% endfor %}
        {% if optionColors[value] %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[value]|generateFontColor }}; background-color:{{ optionColors[value] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[value]|generateBorderColor}}\">{{ translateOption(value, language, field, entityType) }}</span>
        {% else %}
            <code> {{ translateOption(value, language, field, entityType) }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field] == 'multiEnum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
            {% endif %}
        {% endfor %}
        {% for v in value %}
            {% if optionColors[v] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[v]|generateFontColor }}; background-color:{{ optionColors[v] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[v]|generateBorderColor}}\">{{ translateOption(v, language, field, entityType) }}</span>
            {% else %}
                <code style=\"\"> {{ translateOption(v, language, field, entityType) }}</code>&nbsp;&nbsp;
            {% endif %}
        {% endfor %}
     {% elseif updateData['fieldTypes'][field] == 'file' and value != 'Null' %}
        <a href=\"{{ siteUrl }}/#File/view/{{ updateData['attributes'][type][field ~ 'Id'] }}\">{{ value }}</a>
    {% elseif updateData['fieldTypes'][field] == 'link' and value != 'Null' %}
        <a href=\"{{ siteUrl }}/#{{ updateData['linkDefs'][field]['entity'] }}/view/{{ updateData['attributes'][type][field ~ 'Id'] }}\">{{ value }}</a>
     {% elseif updateData['fieldTypes'][field] == 'array' %}
        {{ value|join(', ') }}
    {% else %}
        <code>{{ value }}</code>
    {% endif %}
{% endmacro %}

{% macro getMessage(context) %}
    {% set updateData = context.updateData %}
    {% set language = context.language %}
    {% set entity = context.entity %}
    {% set entityType = context.entityType %}
    {% set entityName = context.entityName %}
    {% set entityUrl = context.entityUrl %}
    {% set actionUser = context.actionUser %}
    {% set notifyUser = context.notifyUser %}
    {% set  hasAssignment = 'assignedUser' in updateData['fields']  %}
    {% set isOnly = updateData['fields']|length == 1 or ('modifiedBy' in updateData['fields'] and  updateData['fields']|length == 2) %}
    {% set assignedUserId =  entity.assignedUserId %}

    {% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId  %}
        {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
        <p>{{ actionUser.name ?? actionUser.userName }} призначив вам {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a>.</p>
    {% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
        <p>c{{ actionUser.name ?? actionUser.userName }} призначив вам і зробив оновлення на {{entityName}} <a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a>.</p>
    {% else %}
        <p>{{ actionUser.name ?? actionUser.userName }} зробив оновлення на {{entityName}}<a href=\"{{entityUrl}}\"><strong>{{entity.name ?? translate('None', language)}}</strong></a>.</p>
    {% endif %}
{% endmacro %}

{%  set shouldShowInLine = updateData['fields']|length == 1 and not updateData['diff'] %}

<div class=\"stream-head-container\">
    <div>
        {% if  shouldShowInLine %}
            {% for field in  updateData['fields'] %}
                <span class=\"text-muted message\">{{ _self.getMessage(_context) }} <code>{{_self.translateField(field, _context)}}</code> {{translate('from', language, 'streamMessages', 'Global')}}&nbsp;<span class=\"was\">{{_self.getValue(field, 'was',_context)}}</span>&nbsp;{{translate('to', language, 'streamMessages')}} <span class=\"became\">{{_self.getValue(field, 'became', _context)}}</span></span>
            {% endfor %}
        {% else %}
            <span class=\"text-muted message\"> {{ _self.getMessage(_context) }} {{ updateData['fields']|map(f => translate(f, language, 'fields', entityType))|map(f => '<code> ' ~ f ~' </code>')|join(', ')|raw }}</span>
            <a href=\"javascript:\" data-action=\"expandDetails\"><span class=\"fas fa-angle-down\"></span></a>
        {% endif %}
    </div>
</div>

<div class=\"hidden details stream-details-container\">

    {% if not shouldShowInLine %}
        {%  set hasNonDiffField = updateData['fields']|keys|length != updateData['diff']|keys|length %}
        {% if hasNonDiffField %}
            <div class=\"panel\">
                {% for field in  updateData['fields'] %}
                    {% if not updateData['diff'][field] %}
                        <div class=\"row\">
                            <div class=\"cell col-md-12 col-lg-6 form-group\">
                                <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('was', language, 'streamMessages')}}</label>
                                <div class=\"field\">{{_self.getValue(field, 'was', _context) }}</div>
                            </div>
                            <div class=\"cell col-md-12 col-lg-6 form-group\">
                                <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('become', language, 'streamMessages')}}</label>
                                <div class=\"field\">{{_self.getValue(field, 'became', _context) }}</div>
                            </div>
                        </div>
                    {% endif %}
                {% endfor %}
            </div>
        {% endif %}
    {% endif%}
    {%if updateData['diff'] %}
        <div class=\"panel diff\">
            {% for field, diff in updateData['diff'] %}
                <div class=\"row\">
                    <div class=\"cell col-md-12 col-lg-12 form-group\">
                        <label class=\"control-label\"><code>{{_self.translateField(field, _context)}}</code> {{translate('changed', language, 'streamMessages')}}</label>
                        <div class=\"field\">{{diff|raw}}</div>
                    </div>
                </div>
            {% endfor %}
        <div>
    {% endif %}
</div>"
                            ]
                        ]
                    ],
                    "email"  => [
                        "id"   => "emailUpdateEntity",
                        "name" => "Entity Updated",
                        "data" => [
                            "field" => [
                                "subject"     => "{% set  hasAssignment = 'assignedUser' in updateData['fields']  %}

{% set isOnly = updateData['fields']|length == 1 or ('modifiedBy' in updateData['fields'] and  updateData['fields']|length == 2) %}

{% set assignedUserId =  entity.assignedUserId %}


{% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId  %}
     {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
    Assigned to you: [{{entityType}}] {{entity.name | raw}}
{% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
    Assigned to you and update [{{entityType}}] {{entity.name | raw}}
{% else %}
    Update: [{{ entityName }}] {{entity.name | raw}}
{% endif %}",
                                "subjectDeDe" => "{% set  hasAssignment = 'assignedUser' in updateData['fields']  %}

{% set isOnly = updateData['fields']|length == 1 %}

{% set assignedUserId =  entity.assignedUserId %}


{% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId  %}
     {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
    Ihnen zugewiesen: [{{entityType}}] {{entity.name | raw}}
{% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
    Ihnen zugewiesen und aktualisieren [{{entityType}}] {{entity.name | raw}}
{% else %}
    Update: [{{ entityName }}] {{entity.name | raw}}
{% endif %}",
                                "subjectUkUa" => "{% set  hasAssignment = 'assignedUser' in updateData['fields']  %}

{% set isOnly = updateData['fields']|length == 1 %}

{% set assignedUserId =  entity.assignedUserId %}


{% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId  %}
     {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
    Ihnen zugewiesen: [{{entityType}}] {{entity.name | raw}}
{% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
    Ihnen zugewiesen und aktualisieren [{{entityType}}] {{entity.name | raw}}
{% else %}
    Update: [{{ entityName }}] {{entity.name | raw}}
{% endif %}",
                                "body"         => "{% set  hasAssignment = 'assignedUser' in updateData['fields'] %}

{% set isOnly = updateData['fields']|length == 1 or ('modifiedBy' in updateData['fields'] and  updateData['fields']|length == 2) %}

{% set assignedUserId =  entity.assignedUserId %}

{% set language = language ?? 'en_US' %}

{% macro getValue(field, type, context)  %}
    {%  set updateData = context.updateData %}
    {%  set language = context.language %}
    {%  set entityType = context.entityType %}
    {%  set fieldDefs = context.updateData['fieldDefs'][field] %}

    {% if updateData['fieldTypes'][field] in ['extensibleEnum', 'link', 'measure', 'file'] %}
        {% set value = updateData['attributes'][type][field ~ 'Name'] %}
    {% elseif updateData['fieldTypes'][field]  == 'bool' %}
         {% set value = updateData['attributes'][type][field] %}
        {%  if value is not null %}
            {% set value = value ?  translate('Yes',language): translate('no',language)  %}
        {% endif %}
    {% else %}
        {% set value = updateData['attributes'][type][field] %}
    {% endif %}

    {% if  updateData['fieldTypes'][field]  == 'extensibleEnum' %}
        {%  set color = updateData['attributes'][type][field ~ 'OptionData']['color'] %}
        {% if color %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ color|generateFontColor }}; background-color:{{ color }};font-size:100%;font-weight:normal; border: solid 1px {{ color|generateBorderColor}}\">{{ value }}</span>
        {% else %}
            <code> {{ value }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field]  == 'extensibleMultiEnum' %}
        {% for optionData in updateData['attributes'][type][field ~ 'OptionsData'] %}
            {% if optionData['color'] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionData['color']|generateFontColor }}; background-color:{{ optionData['color'] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionData['color']|generateBorderColor}}\">{{ optionData['name'] }}</span>
            {% else %}
                <code style=\"background-color:{{ optionData['color'] }}\"> {{ optionData['name'] }}</code> &nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData['fieldTypes'][field] == 'color' %}
        <code style=\"color:{{ value|generateFontColor }}; background-color:{{ value }}\"> {{ value }}</code>
    {% elseif updateData['fieldTypes'][field] == 'enum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
        {% endfor %}
        {% if optionColors[value] %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[value]|generateFontColor }}; background-color:{{ optionColors[value] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[value]|generateBorderColor}}\">{{ translateOption(value, language, field, entityType) }}</span>
        {% else %}
            <code> {{ translateOption(value, language, field, entityType) }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field] == 'multiEnum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
        {% endfor %}
        {% for v in value %}
            {% if optionColors[v] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[v]|generateFontColor }}; background-color:{{ optionColors[v] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[v]|generateBorderColor}}\">{{ translateOption(v, language, field, entityType) }}</span>
            {% else %}
                <code style=\"\"> {{ translateOption(v, language, field, entityType) }}</code>&nbsp;&nbsp;
            {% endif %}
        {% endfor %}
    {% else %}
        {{ value }}
    {% endif %}
{% endmacro %}


{% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId %}
    {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
    <p> {{ actionUser.name }} has assigned {{ entityName }} to  you.</p>
{% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
    <p> {{ actionUser.name }} has assigned to you and made update on {{ entityName }}.</p>
{% else %}
    <p>{{ actionUser.name }} made update on {{ entityName }}.</p>
{% endif %}
<table>
    <thead>
    <tr>
        <th></th>
        <th></th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    {% for field in updateData['fields'] %}
        {% if not updateData['diff'][field] %}
        <tr>
            <td> <span style=\"padding: 15px 0\"> {{ translate(field, language, 'fields', entityType ) }}:</span></td>
            {%  set hasWasValue = updateData['attributes']['was'][field] or updateData['attributes']['was'][field ~ 'Name'] %}
            {%  set hasBeforeValue = updateData['attributes']['became'][field] or updateData['attributes']['became'][field ~ 'Name'] %}
            {% if hasWasValue %}<td style=\"padding: 10px 0;\"  {% if not hasBeforeValue %} rowspan=\"2\" {% endif %}><span style=\"padding:3px 5px; background-color: #F5A8A844;text-decoration: line-through;\">{{ _self.getValue(field, 'was', _context) }} </span></td> {% endif %}
            {% if hasBeforeValue %}<td style=\"padding: 10px 0;\" {% if not hasWasValue %} rowspan=\"2\" {% endif %}><span style=\"padding:3px 5px; background-color: #A8F5B851;\">{{  _self.getValue(field, 'became', _context)  }}</span></td>{% endif %}
        <tr>
        {% endif %}
    {% endfor %}
    </tbody>
</table>
<div>
    <style>
        ins {
            color: green;
            background: #dfd;
            text-decoration: none;
        }
        del {
            color: red;
            background: #fdd;
            text-decoration: none;
        }
    </style>
    {% for field, diff in updateData['diff'] %}
        <div style=\"margin: 15px 0\">    <span style=\"padding: 15px 0; margin-right:15px;\"> {{ translate(field, language, 'fields', entityType ) }}: </span> {{ diff|raw }}</div>
        <br>
    {% endfor %}
</div>
<p><a href=\"{{ entityUrl }}\">View</a></p>
",
                                "bodyDeDe"    =>"{% set  hasAssignment = 'assignedUser' in updateData['fields'] %}

{% set isOnly = updateData['fields']|length == 1 or ('modifiedBy' in updateData['fields'] and  updateData['fields']|length == 2) %}

{% set assignedUserId =  entity.assignedUserId %}

{% set language = language ?? 'en_US' %}

{% macro getValue(field, type, context)  %}
    {%  set updateData = context.updateData %}
    {%  set language = context.language %}
    {%  set entityType = context.entityType %}
    {%  set fieldDefs = context.updateData['fieldDefs'][field] %}

    {% if updateData['fieldTypes'][field] in ['extensibleEnum', 'link', 'measure', 'file'] %}
        {% set value = updateData['attributes'][type][field ~ 'Name'] %}
    {% elseif updateData['fieldTypes'][field]  == 'bool' %}
        {% set value = updateData['attributes'][type][field] %}
        {%  if value is not null %}
            {% set value = value ?  translate('Yes',language): translate('no',language)  %}
        {% endif %}
    {% else %}
        {% set value = updateData['attributes'][type][field] %}
    {% endif %}

    {% if  updateData['fieldTypes'][field]  == 'extensibleEnum' %}
        {%  set color = updateData['attributes'][type][field ~ 'OptionData']['color'] %}
        {% if color %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ color|generateFontColor }}; background-color:{{ color }};font-size:100%;font-weight:normal; border: solid 1px {{ color|generateBorderColor}}\">{{ value }}</span>
        {% else %}
            <code> {{ value }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field]  == 'extensibleMultiEnum' %}
        {% for optionData in updateData['attributes'][type][field ~ 'OptionsData'] %}
            {% if optionData['color'] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionData['color']|generateFontColor }}; background-color:{{ optionData['color'] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionData['color']|generateBorderColor}}\">{{ optionData['name'] }}</span>
            {% else %}
                <code style=\"background-color:{{ optionData['color'] }}\"> {{ optionData['name'] }}</code> &nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData['fieldTypes'][field] == 'color' %}
        <code style=\"color:{{ value|generateFontColor }}; background-color:{{ value }}\"> {{ value }}</code>
    {% elseif updateData['fieldTypes'][field] == 'enum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
        {% endfor %}
        {% if optionColors[value] %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[value]|generateFontColor }}; background-color:{{ optionColors[value] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[value]|generateBorderColor}}\">{{ translateOption(value, language, field, entityType) }}</span>
        {% else %}
            <code> {{ translateOption(value, language, field, entityType) }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field] == 'multiEnum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
        {% endfor %}
        {% for v in value %}
            {% if optionColors[v] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[v]|generateFontColor }}; background-color:{{ optionColors[v] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[v]|generateBorderColor}}\">{{ translateOption(v, language, field, entityType) }}</span>
            {% else %}
                <code style=\"\"> {{ translateOption(v, language, field, entityType) }}</code>&nbsp;&nbsp;
            {% endif %}
        {% endfor %}
    {% else %}
        {{ value }}
    {% endif %}
{% endmacro %}


{% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId%}
    {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
    <p>  {{ actionUser.name }} hat Ihnen {{ entityName }} zugewiesen. </p>
{% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
    <p> {{ actionUser.name }} hat Ihnen zugewiesen und eine Aktualisierung an {{ entityName }} vorgenommen </p>
{% else %}
    <p>{{ actionUser.name }} hat eine Aktualisierung an {{ entityName }} vorgenommen.</p>
{% endif %}

<table>
    <thead>
    <tr>
        <th></th>
        <th></th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    {% for field in updateData['fields'] %}
        {% if not updateData['diff'][field] %}
        <tr>
            <td> <span style=\"padding: 15px 0\"> {{ translate(field, language, 'fields', entityType ) }}:</span></td>
            {%  set hasWasValue = updateData['attributes']['was'][field] or updateData['attributes']['was'][field ~ 'Name'] %}
            {%  set hasBeforeValue = updateData['attributes']['became'][field] or updateData['attributes']['became'][field ~ 'Name'] %}
            {% if hasWasValue %}<td style=\"padding: 10px 0;\"  {% if not hasBeforeValue %} rowspan=\"2\" {% endif %}><span style=\"padding:3px 5px; background-color: #F5A8A844;text-decoration: line-through;\">{{ _self.getValue(field, 'was', _context) }} </span></td> {% endif %}
            {% if hasBeforeValue %}<td style=\"padding: 10px 0;\" {% if not hasWasValue %} rowspan=\"2\" {% endif %}><span style=\"padding:3px 5px; background-color: #A8F5B851;\">{{  _self.getValue(field, 'became', _context)  }}</span></td>{% endif %}
        <tr>
        {% endif %}
    {% endfor %}
    </tbody>
</table>
<div>
    <style>
        ins {
            color: green;
            background: #dfd;
            text-decoration: none;
        }
        del {
            color: red;
            background: #fdd;
            text-decoration: none;
        }
    </style>
    {% for field, diff in updateData['diff'] %}
        <div style=\"margin: 15px 0\">    <span style=\"padding: 15px 0; margin-right:15px;\"> {{ translate(field, language, 'fields', entityType ) }}: </span> {{ diff|raw }}</div>
        <br>
    {% endfor %}
</div>
<p><a href=\"{{ entityUrl }}\">Siehe</a></p>
",
                                "bodyUkUa"    =>"{% set  hasAssignment = 'assignedUser' in updateData['fields'] %}

{% set isOnly = updateData['fields']|length == 1 or ('modifiedBy' in updateData['fields'] and  updateData['fields']|length == 2) %}

{% set assignedUserId =  entity.assignedUserId %}

{% set language = language ?? 'en_US' %}

{% macro getValue(field, type, context)  %}
    {%  set updateData = context.updateData %}
    {%  set language = context.language %}
    {%  set entityType = context.entityType %}
    {%  set fieldDefs = context.updateData['fieldDefs'][field] %}

    {% if updateData['fieldTypes'][field] in ['extensibleEnum', 'link', 'measure', 'file'] %}
        {% set value = updateData['attributes'][type][field ~ 'Name'] %}
    {% elseif updateData['fieldTypes'][field]  == 'bool' %}
         {% set value = updateData['attributes'][type][field] %}
        {%  if value is not null %}
            {% set value = value ?  translate('Yes',language): translate('no',language)  %}
        {% endif %}
    {% else %}
        {% set value = updateData['attributes'][type][field] %}
    {% endif %}

    {% if  updateData['fieldTypes'][field]  == 'extensibleEnum' %}
        {%  set color = updateData['attributes'][type][field ~ 'OptionData']['color'] %}
        {% if color %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ color|generateFontColor }}; background-color:{{ color }};font-size:100%;font-weight:normal; border: solid 1px {{ color|generateBorderColor}}\">{{ value }}</span>
        {% else %}
            <code> {{ value }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field]  == 'extensibleMultiEnum' %}
        {% for optionData in updateData['attributes'][type][field ~ 'OptionsData'] %}
            {% if optionData['color'] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionData['color']|generateFontColor }}; background-color:{{ optionData['color'] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionData['color']|generateBorderColor}}\">{{ optionData['name'] }}</span>
            {% else %}
                <code style=\"background-color:{{ optionData['color'] }}\"> {{ optionData['name'] }}</code> &nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData['fieldTypes'][field] == 'color' %}
        <code style=\"color:{{ value|generateFontColor }}; background-color:{{ value }}\"> {{ value }}</code>
    {% elseif updateData['fieldTypes'][field] == 'enum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
        {% endfor %}
        {% if optionColors[value] %}
            <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[value]|generateFontColor }}; background-color:{{ optionColors[value] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[value]|generateBorderColor}}\">{{ translateOption(value, language, field, entityType) }}</span>
        {% else %}
            <code> {{ translateOption(value, language, field, entityType) }}</code>
        {% endif %}
    {% elseif updateData['fieldTypes'][field] == 'multiEnum' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs['options'] %}
            {%  set color = fieldDefs['optionColors'][loop.index0] %}
            {% set optionColors = optionColors|merge({(option): (color and '#' in color) ? color : '#'~color }) %}
        {% endfor %}
        {% for v in value %}
            {% if optionColors[v] %}
                <span class=\"label colored-multi-enum\" style=\"color:{{ optionColors[v]|generateFontColor }}; background-color:{{ optionColors[v] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[v]|generateBorderColor}}\">{{ translateOption(v, language, field, entityType) }}</span>
            {% else %}
                <code style=\"\"> {{ translateOption(v, language, field, entityType) }}</code>&nbsp;&nbsp;
            {% endif %}
        {% endfor %}
    {% else %}
        {{ value }}
    {% endif %}
{% endmacro %}



{% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId%}
    {% set assignedUserName =  updateData['attributes']['became']['assignedUserName'] %}
    <p> {{ actionUser.name }} призначив вам {{ entityName }}</p>
{% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
    <p>{{ actionUser.name }} призначив вам і оновив {{ entityName }}</p>
{% else %}
    <p>{{ actionUser.name }} виконано оновлення на {{ entityName }}.</p>
{% endif %}

<table>
    <thead>
    <tr>
        <th></th>
        <th></th>
        <th></th>
    </tr>
    </thead>
    <tbody>
   {% for field in updateData['fields'] %}
        {% if not updateData['diff'][field] %}
        <tr>
            <td> <span style=\"padding: 15px 0\"> {{ translate(field, language, 'fields', entityType ) }}:</span></td>
            {%  set hasWasValue = updateData['attributes']['was'][field] or updateData['attributes']['was'][field ~ 'Name'] %}
            {%  set hasBeforeValue = updateData['attributes']['became'][field] or updateData['attributes']['became'][field ~ 'Name'] %}
            {% if hasWasValue %}<td style=\"padding: 10px 0;\"  {% if not hasBeforeValue %} rowspan=\"2\" {% endif %}><span style=\"padding:3px 5px; background-color: #F5A8A844;text-decoration: line-through;\">{{ _self.getValue(field, 'was', _context) }} </span></td> {% endif %}
            {% if hasBeforeValue %}<td style=\"padding: 10px 0;\" {% if not hasWasValue %} rowspan=\"2\" {% endif %}><span style=\"padding:3px 5px; background-color: #A8F5B851;\">{{  _self.getValue(field, 'became', _context)  }}</span></td>{% endif %}
        <tr>
        {% endif %}
    {% endfor %}
    </tbody>
</table>
<div>
    <style>
        ins {
            color: green;
            background: #dfd;
            text-decoration: none;
        }
        del {
            color: red;
            background: #fdd;
            text-decoration: none;
        }
    </style>
    {% for field, diff in updateData['diff'] %}
        <div style=\"margin: 15px 0\">    <span style=\"padding: 15px 0; margin-right:15px;\"> {{ translate(field, language, 'fields', entityType ) }}: </span> {{ diff|raw }}</div>
        <br>
    {% endfor %}
</div>
<p><a href=\"{{ entityUrl }}\">Вигляд</a></p>"
                            ]
                        ]
                    ]
                ]
            ],
            [
                "id"                      => IdGenerator::uuid(),
                "name"                    => "Note Creation Without parent",
                "entity"                  => 'Note',
                "occurrence"              => 'creation',
                "notification_profile_id" => $defaultProfileId,
                "is_active"               => true,
                "ignore_self_action"      => true,
                "as_owner"                => true,
                "as_follower"             => true,
                "as_assignee"             => true,
                "as_team_member"          => true,
                "as_notification_profile" => false,
                "data"                    => [
                    "field" => [
                        "systemActive"     => true,
                        "emailActive"      => true,
                        "systemTemplateId" => "systemNotePost",
                        "emailTemplateId"  => "emailNotePost"
                    ],
                ],
            ],
            [
                "id"                      => IdGenerator::uuid(),
                "name"                    => "Note Creation in Entity",
                "entity"                  => '',
                "occurrence"              => 'note_created',
                "notification_profile_id" => $defaultProfileId,
                "is_active"               => true,
                "ignore_self_action"      => true,
                "as_owner"                => true,
                "as_follower"             => true,
                "as_assignee"             => true,
                "as_team_member"          => false,
                "as_notification_profile" => false,
                "data"                    => [
                    "field" => [
                        "systemActive"     => true,
                        "emailActive"      => true,
                        "systemTemplateId" => "systemNotePost",
                        "emailTemplateId"  => "emailNotePost"
                    ],
                ],
                "templates"               => [
                    "system" => [
                        'id'   => 'systemNotePost',
                        'name' => 'Note Creation',
                        'data' => [
                            'field' => [
                                "body"     => '<p>{{ actionUser.name ?? actionUser.userName }} posted  {% if parent %}  on {{parentName}} <a href="{{parentUrl}}">{{parent.name}}</a>. {% endif %}</p>
<p>{{entity.data.post}}</p>
{% if not parent %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe" => '<p>{{ actionUser.name ?? actionUser.userName }} auf{% if parent %}  {{parentName}} <a href="{{parentUrl}}">{{parent.name}}</a> gepostet. {% endif %}</p>
<p>{{entity.data.post}}</p>
{% if not parent %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyUkUa" => '<p>{{ actionUser.name ?? actionUser.userName }} опублікував {% if parent %} на {{parentName}} <a href="{{parentUrl}}">{{parent.name}}</a>. {% endif %}</p> <p>
<p>{{entity.data.post}}</p>
{% if not parent %}
<p><a href="{{siteUrl}}/#Stream">Вигляд</a></p>
{% endif %}'
                            ]
                        ]
                    ],
                    "email"  => [
                        'id'   => 'emailNotePost',
                        "name" => "Note creation",
                        "data" => [
                            "field" => [
                                "subject"     => 'Post: [{{ parentName }}] {{parent.name | raw}}',
                                "subjectDeDe" => 'Post: [{{ parentName }}] {{parent.name | raw}}',
                                "subjectUkUa" => 'Post: [{{ parentName }}] {{parent.name | raw}}',
                                "body"        => '<p>{{ actionUser.name ?? actionUser.userName }} posted  {% if parent %}  on {{parentName}} {{parent.name}}. {% endif %}</p>
<p>{{entity.data.post | raw}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe"    => '<p>{{ actionUser.name ?? actionUser.userName }} auf{% if parent %}  {{parentName}} {{parent.name}} gepostet. {% endif %}</p>
<p>{{entity.data.post  | raw}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyUkUa"    => '<p>{{ actionUser.name ?? actionUser.userName }} опублікував {% if parent %} на {{parentName}} {{parent.name}}. {% endif %}</p> <p>
<p>{{entity.data.post | raw}}</p>
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
                "id"                      => IdGenerator::uuid(),
                "name"                    => "Mention",
                "entity"                  => '',
                "occurrence"              => 'mentioned',
                "notification_profile_id" => $defaultProfileId,
                "is_active"               => true,
                "ignore_self_action"      => true,
                "as_owner"                => false,
                "as_follower"             => false,
                "as_assignee"             => false,
                "as_team_member"          => false,
                "as_notification_profile" => false,
                "data"                    => [
                    "field" => [
                        "systemActive"     => true,
                        "emailActive"      => true,
                        "systemTemplateId" => "systemMention",
                        "emailTemplateId"  => "emailMention"
                    ]
                ],
                "templates"               => [
                    "system" => [
                        "id"   => "systemMention",
                        "name" => 'Mention',
                        "data" => [
                            "field" => [
                                "body"     => '<p>You were mentioned in post by {{ actionUser.name ?? actionUser.userName }}.</p>
{% if parent %}
<p>Related to: {{parentName}} <a href="{{parentUrl}}">{{parent.name}}</a></p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if not parent %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe" => '<p>Sie wurden in einem Beitrag von {{ actionUser.name ?? actionUser.userName }} erwähnt.</p>
{% if parent %}
<p>Verwandt mit:  {{parentName}} <a href="{{parentUrl}}">{{parent.name}}</a></p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if not parent %}
<p><a href="{{siteUrl}}/#Stream">Siehe</a></p>
{% endif %}',
                                "bodyUkUa" => '<p>Вас було згадано у дописі користувача {{ actionUser.name ?? actionUser.userName }}.</p>{% if parent %}
<p>Пов\'язано з:  {{parentName}} <a href="{{parentUrl}}">{{parent.name}}</a></p>
{% endif  %}
<p>{{entity.data.post}}</p>
{% if not parent %}
<p><a href="{{siteUrl}}/#Stream">Вигляд</a></p>
{% endif %}'
                            ]
                        ]
                    ],
                    "email"  => [
                        "id"   => "emailMention",
                        "name" => "Mention",
                        "data" => [
                            "field" => [
                                "subject"     => "You were mentioned",
                                "subjectDeDe" => "Sie wurden erwähnt",
                                "subjectUkUa" => "Тебе згадували",
                                "body"        => '<p>You were mentioned in post by {{ actionUser.name ?? actionUser.userName }}.</p>
{% if parent %}
<p>Related to: {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post | raw}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">View</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">View</a></p>
{% endif %}',
                                "bodyDeDe"    => '
 <p>Sie wurden in einem Beitrag von {{ actionUser.name ?? actionUser.userName }} erwähnt.</p>
{% if parent %}
<p>Verwandt mit:  {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post | raw}}</p>
{% if parent %}
<p><a href="{{parentUrl}}">Siehe</a></p>
{% else %}
<p><a href="{{siteUrl}}/#Stream">Siehe</a></p>
{% endif %}',
                                "bodyUkUa"    => '<p>Вас було згадано у дописі користувача {{ actionUser.name ?? actionUser.userName }}.</p>{% if parent %}
<p>Пов\'язано з:  {{parentName}}</p>
{% endif  %}
<p>{{entity.data.post | raw}}</p>
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
                "id"                      => IdGenerator::uuid(),
                "name"                    => "Assignment/Ownership",
                "entity"                  => '',
                "occurrence"              => 'ownership_assignment',
                "notification_profile_id" => $defaultProfileId,
                "is_active"               => true,
                "ignore_self_action"      => true,
                "as_owner"                => true,
                "as_follower"             => false,
                "as_assignee"             => true,
                "as_team_member"          => false,
                "as_notification_profile" => false,
                "data"                    => [
                    "field" => [
                        "systemActive"     => true,
                        "emailActive"      => true,
                        "systemTemplateId" => "systemOwnerAssign",
                        "emailTemplateId"  => "emailOwnerAssign"
                    ],
                ],
                "templates"               => [
                    "system" => [
                        "id"   => 'systemOwnerAssign',
                        "type" => "system",
                        "name" => "Assignment/Ownership",
                        "data" => [
                            "field" => [
                                "body"     => '{% if isAssignment %}
<p>{{ actionUser.name ?? actionUser.userName }} has assigned {{entityName}} to  {% if notifyUser.id == assignedUser.id %} you. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><a href="{{entityUrl}}"><strong>{{entity.name}}</strong></a></p>
{% else %}
<p>{{ actionUser.name ?? actionUser.userName }} has set {% if notifyUser.id == ownerUser.id %} you {% else %}  {{ownerUser.name}} {% endif %} as owner for {{entityName}}.</p>
<p><a href="{{entityUrl}}"><strong>{{entity.name}}</strong></a></p>
{% endif %}
',
                                "bodyDeDe" => '
{% if  isAssignment %}
<p>{{ actionUser.name ?? actionUser.userName }} hat Ihnen {{entityName}}   {% if notifyUser.id == assignedUser.id %} zugewiesen. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><a href="{{entityUrl}}"><strong>{{entity.name}}</strong></a></p>
{% else %}
<p>{{ actionUser.name ?? actionUser.userName }} hat {% if notifyUser.id == ownerUser.id %} Sie {% else %}  {{ownerUser.name}} {% endif %} als Eigentümer für {{entityName}}.
<p><a href="{{entityUrl}}"><strong>{{entity.name}}</strong></a></p>
{% endif %} 
',
                                "bodyUkUa" => '
{% if isAssignment %}
<p>{{ actionUser.name ?? actionUser.userName }} призначив {% if notifyUser.id == assignedUser.id %} вам {% else %} {{assignedUser.name}}. {% endif %} {{entityName}}.</p>
<p><a href="{{entityUrl}}"><strong>{{entity.name}}</strong></a></p>
{% else %}
<p>{{ actionUser.name ?? actionUser.userName }} встановив {% if notifyUser.id == ownerUser.id %} вам {% else %}  {{ownerUser.name}} {% endif  %} як власника для {{entityName}}.</p>
<p><a href="{{entityUrl}}"><strong>{{entity.name}}</strong></a></p>
{% endif %}
                              '
                            ]
                        ]
                    ],
                    "email"  => [
                        "id"   => 'emailOwnerAssign',
                        "type" => "system",
                        "name" => "Assignment/Ownership",
                        "data" => [
                            "field" => [
                                "subject"     => '{% if isAssignment %}
Assigned to {% if notifyUser.id == assignedUser.id %} you {% else %}  {{assignedUser.name | raw}} {% endif %}: [{{entityType}}] {{entity.name | raw}}
{% else %}
Marked as owner: [{{entityType}}] {{entity.name | raw}}
{% endif %}',
                                "subjectDeDe" => '{% if isAssignment  %}
Ihnen {% if notifyUser.id == assignedUser.id %} zugewiesen. {% else %}  {{assignedUser.name | raw}} {% endif %} : [{{entityType}}] {{entity.name | raw}}
{% else %}
Markiert als Eigentümer: [{{entityType}}] {{entity.name | raw}}
{% endif %}',
                                "subjectUkUa" => '{% if isAssignment %}
Призначено {% if notifyUser.id == assignedUser.id %} вам {% else %} {{assignedUser.name | raw}} {% endif %}: [{{entityType}}] {{entity.name | raw}}
{% else %}
Позначено як власник: [{{entityType}}] {{entity.name | raw}}
{% endif %}',
                                "body"        => '{% if isAssignment %}
<p>{{ actionUser.name ?? actionUser.userName }} has assigned {{entityName}} to  {% if notifyUser.id == assignedUser.id %} you. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">View</a></p>
{% else %}
<p>{{ actionUser.name ?? actionUser.userName }} has set {% if notifyUser.id == ownerUser.id %} you {% else %}  {{ownerUser.name}} {% endif %} as owner for {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">View</a></p>
{% endif %}
',
                                "bodyDeDe"    => '
{% if isAssignment %}
<p>{{ actionUser.name ?? actionUser.userName }} hat Ihnen {{entityName}}   {% if notifyUser.id == assignedUser.id %} zugewiesen. {% else %}  {{assignedUser.name}}. {% endif %} </p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Siehe</a></p>
{% else %}
<p>{{ actionUser.name ?? actionUser.userName }} hat {% if notifyUser.id == ownerUser.id %} Sie {% else %}  {{ownerUser.name}} {% endif %} als Eigentümer für {{entityName}}.</p><p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Siehe</a></p>
{% endif %} 
',
                                "bodyUkUa"    => '
{% if isAssignment %}
<p>{{ actionUser.name ?? actionUser.userName }} призначив {% if notifyUser.id == assignedUser.id %} вам {% else %} {{assignedUser.name}}. {% endif %} {{entityName}}.</p>
<p><strong>{{entity.name}}</strong></p>
<p><a href="{{entityUrl}}">Вигляд</a></p>
{% else %}
<p>{{ actionUser.name ?? actionUser.userName }} встановив {% if notifyUser.id == ownerUser.id %} вам {% else %}  {{ownerUser.name}} {% endif  %} як власника для {{entityName}}.</p>
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
    }

    private function getPasswordEmailTemplates(): array
    {
        $datetime = (new \DateTime())->format('Y-m-d H:i:s');

        return [
            'emailPasswordChangeRequest' => [
                'id'               => IdGenerator::unsortableId(),
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
                'id'               => IdGenerator::unsortableId(),
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