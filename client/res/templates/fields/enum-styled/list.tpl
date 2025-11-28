{{#if isNotEmpty}}
<span class="text-{{style}}">{{translateOption value scope=scope field=name}}</span>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}} <span class="pre-label"> </span>{{/if}}
{{/if}}