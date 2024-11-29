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

class V1Dot11Dot52 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-11-29 10:00:00');
    }

    public function up(): void
    {
        $templateId = 'systemUpdateEntity';
        $result = $this->getConnection()->createQueryBuilder()
            ->select('data')
            ->from('notification_template')
            ->where('id = :id')
            ->setParameter('id', $templateId)
            ->fetchAssociative();

        if(!empty($result['data'])){
            $data = @json_decode($result['data'], true);
        }

        if(!empty($data['field']['body'])){
            $data['field']['body'] = self::getNewTemplates();
            $this->getConnection()->createQueryBuilder()
                ->update('notification_template')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('data', json_encode($data))
                ->setParameter('id', $templateId)
                ->executeStatement();
        }
    }

    public function down(): void
    {
    }

    public static function getNewTemplates() :string {
        return '{%  set shouldShowInLine = updateData[\'fields\']|length == 1 and not updateData[\'diff\'] %}

<div class="stream-head-container">
    <div>
        {% if  shouldShowInLine %}
            {% for field in  updateData[\'fields\'] %}
                <span class="text-muted message">{{ _self.getMessage(_context) }} <code>{{_self.translateField(field, _context)}}</code> {{translate(\'from\', language, \'streamMessages\', \'Global\')}}&nbsp;<span class="was">{{_self.getValue(field, \'was\',_context)}}</span>&nbsp;{{translate(\'to\', language, \'streamMessages\')}} <span class="became">{{_self.getValue(field, \'became\', _context)}}</span></span>
            {% endfor %}
        {% else %}
            <span class="text-muted message"> {{ _self.getMessage(_context) }} {{ updateData[\'fields\']|map(f => translate(f, language, \'fields\', entityType))|map(f => \'<code> \' ~ f ~\' </code>\')|join(\', \')|raw }}</span>
        {% endif %}
    </div>
</div>


{% macro getMessage(context) %}
    {% set updateData = context.updateData %}
    {% set language = context.language %}
    {% set entity = context.entity %}
    {% set entityType = context.entityType %}
    {% set entityName = context.entityName %}
    {% set entityUrl = context.entityUrl %}
    {% set actionUser = context.actionUser %}
    {% set notifyUser = context.notifyUser %}
    {% set  hasAssignment = \'assignedUser\' in updateData[\'fields\']  %}
    {% set isOnly = updateData[\'fields\']|length == 1 or (\'modifiedBy\' in updateData[\'fields\'] and  updateData[\'fields\']|length == 2) %}
    {% set assignedUserId =  entity.assignedUserId %}

    {% if hasAssignment  and isOnly and assignedUserId and notifyUser.id == assignedUserId  %}
        {% set assignedUserName =  updateData[\'attributes\'][\'became\'][\'assignedUserName\'] %}
        {{ actionUser.name ?? actionUser.userName }} has assigned to you  {{entityName}} <a href="{{entityUrl}}"><strong>{{entity.name ?? translate(\'None\', language)}}</strong></a>.
    {% elseif  hasAssignment  and notifyUser.id == assignedUserId %}
        {{ actionUser.name ?? actionUser.userName }} has assigned to you {{entityName}} <a href="{{entityUrl}}"><strong>{{entity.name ?? translate(\'None\', language)}}</strong></a> and updated
    {% else %}
        {{ actionUser.name ?? actionUser.userName }}  in {{entityName}} <a href="{{entityUrl}}"><strong>{{entity.name ?? translate(\'None\', language)}}</strong></a>  updated
    {% endif %}
{% endmacro %}

{% macro translateField(field, context) %}
    {{ translate(field, context.language, \'fields\', context.entityType) }}
{% endmacro %}

{% macro getValue(field, type, context)  %}
    {%  set updateData = context.updateData %}
    {%  set language = context.language %}
    {%  set entityType = context.entityType %}
    {%  set fieldDefs = context.updateData[\'fieldDefs\'][field] %}

    {% if updateData[\'fieldTypes\'][field] in [\'extensibleEnum\', \'link\', \'measure\', \'file\'] %}
        {% set value = updateData[\'attributes\'][type][field ~ \'Name\'] %}
    {% elseif updateData[\'fieldTypes\'][field]  == \'bool\' %}
        {% set value = updateData[\'attributes\'][type][field] %}
        {%  if value is not null %}
            {% set value = value ?  translate(\'Yes\',language): translate(\'no\',language)  %}
        {% endif %}
    {% else %}
        {% set value = updateData[\'attributes\'][type][field] %}
    {% endif %}

    {% if value is null %}
        {% set value = \'Null\' %}
    {% endif %}

    {% if  updateData[\'fieldTypes\'][field]  == \'extensibleEnum\' %}
        {%  set color = updateData[\'attributes\'][type][field ~ \'OptionData\'][\'color\'] %}
        {% if color %}
            <span class="label colored-multi-enum" style="color:{{ color|generateFontColor }}; background-color:{{ color }};font-size:100%;font-weight:normal; border: solid 1px {{ color|generateBorderColor}}">{{ value }}</span>
        {% else %}
            <code> {{ value }}</code>
        {% endif %}
    {% elseif updateData[\'fieldTypes\'][field]  == \'extensibleMultiEnum\' %}
        {% for optionData in updateData[\'attributes\'][type][field ~ \'OptionsData\'] %}
            {% if optionData[\'color\'] %}
                <span class="label colored-multi-enum" style="color:{{ optionData[\'color\']|generateFontColor }}; background-color:{{ optionData[\'color\'] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionData[\'color\']|generateBorderColor}}">{{  optionData[\'name\'] }}</span>
            {% else %}
                <code style="background-color:{{ optionData[\'color\'] }}"> {{ optionData[\'name\'] }}</code> &nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData[\'fieldTypes\'][field] == \'color\' %}
        <code style="color:{{ value|generateFontColor }}; background-color:{{ value }}"> {{ value }}</code>
    {% elseif updateData[\'fieldTypes\'][field] == \'enum\' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs[\'options\'] %}
            {%  set color = fieldDefs[\'optionColors\'][loop.index0] %}
            {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and \'#\' in color) ? color : \'#\'~color }) %}
            {% endif %}
        {% endfor %}
        {% if optionColors[value] %}
            <span class="label colored-multi-enum" style="color:{{ optionColors[value]|generateFontColor }}; background-color:{{ optionColors[value] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[value]|generateBorderColor}}">{{ translateOption(value, language, field, entityType) }}</span>
        {% else %}
            <code> {{ translateOption(value, language, field, entityType) }}</code>
        {% endif %}
    {% elseif updateData[\'fieldTypes\'][field] == \'multiEnum\' %}
        {%  set optionColors = {} %}
        {% for option in fieldDefs[\'options\'] %}
            {%  set color = fieldDefs[\'optionColors\'][loop.index0] %}
            {% if color %}
                {% set optionColors = optionColors|merge({(option): (color and \'#\' in color) ? color : \'#\'~color }) %}
            {% endif %}
        {% endfor %}
        {% for v in value %}
            {% if optionColors[v] %}
                <span class="label colored-multi-enum" style="color:{{ optionColors[v]|generateFontColor }}; background-color:{{ optionColors[v] }};font-size:100%;font-weight:normal; border: solid 1px {{ optionColors[v]|generateBorderColor}}">{{ translateOption(v, language, field, entityType) }}</span>
            {% else %}
                <code style=""> {{ translateOption(v, language, field, entityType) }}</code>&nbsp;&nbsp;
            {% endif %}
        {% endfor %}
    {% elseif updateData[\'fieldTypes\'][field] == \'file\' and value != \'Null\' %}
        <a href="{{ siteUrl }}/#File/view/{{ updateData[\'attributes\'][type][field ~ \'Id\'] }}">{{ value }}</a>
    {% elseif updateData[\'fieldTypes\'][field] == \'link\' and value != \'Null\' %}
        <a href="{{ siteUrl }}/#{{ updateData[\'linkDefs\'][field][\'entity\'] }}/view/{{ updateData[\'attributes\'][type][field ~ \'Id\'] }}">{{ value }}</a>
    {% elseif updateData[\'fieldTypes\'][field] == \'array\' %}
        {{ value|join(\', \') }}
    {% else %}
        <code>{{ value }}</code>
    {% endif %}
{% endmacro %}
';
    }
    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
